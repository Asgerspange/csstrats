"""Demo processing service for handling uploads and scheduling."""

import asyncio
import gc
import gzip
import json
import aiomysql
from loguru import logger
from typing import Optional

from config import AppConfig
from demo_processor import DemoProcessor

class DemoService:
    """Service for managing demo processing operations."""
    
    def __init__(self, config: AppConfig):
        self.config = config
        self.demo_processor = DemoProcessor(config.steam)
        self._pool: Optional[aiomysql.Pool] = None

    async def get_pool(self) -> aiomysql.Pool:
        """Lazily initialize and return the connection pool."""
        if self._pool is None:
            logger.info("Initializing MySQL connection pool...")
            self._pool = await aiomysql.create_pool(
                host=self.config.database.host,
                port=self.config.database.port,
                user=self.config.database.username,
                password=self.config.database.password,
                db=self.config.database.database,
                minsize=1,
                maxsize=50,  # Safety margin: 12 used, 3 free for other apps
                autocommit=True,
                connect_timeout=60
            )
        return self._pool
    async def process_debug_demo(self) -> Optional[int]:
        if not self.config.debug.enabled: return None
        pool = await self.get_pool()
        try:
            demo_id = await self.demo_processor.process_demo_from_file(
                self.config.debug.demo_file_path,
                pool,
                self.config.debug.user_id,
                self.config.debug.demo_name,
                self.config.debug.save,
                self.config.version
            )
            return demo_id
        except BaseException as e:
            logger.exception(f"Debug processing failed: {e}")
            return None

    async def check_and_process_demos(self, scheduler=None) -> bool:
        pool = await self.get_pool()
        
        # We use a fresh connection each check to avoid 'stale' snapshots
        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                # Debug: Let's see if we can see ANY demos
                await cursor.execute("SELECT COUNT(*) as total FROM queued_demos")
                count_res = await cursor.fetchone()
                logger.debug(f"Database check: {count_res['total']} total rows found in queued_demos")

                await cursor.execute("""
                    SELECT id, name, status, metadata, owner FROM queued_demos
                    WHERE status = 'pending' 
                    LIMIT 1
                """)
                pending_upload = await cursor.fetchone()

        if pending_upload:
            # If using DictCursor, access via keys
            q_id = pending_upload['id']
            name = pending_upload['name']
            owner = pending_upload['owner']
            metadata = pending_upload['metadata']

            if scheduler: scheduler.pause()
            
            try:
                logger.info(f"Processing upload {q_id}: {name}")
                
                async with pool.acquire() as conn:
                    async with conn.cursor() as cursor:
                        # Update to processing immediately
                        await cursor.execute("UPDATE queued_demos SET status = 'processing' WHERE id = %s", (q_id,))
                        # No need for manual commit if autocommit=True, but it doesn't hurt
                
                # Heavy lifting
                demo_id = await self.demo_processor.process_demo_from_chunks(
                    q_id, pool, owner, name, self.config.version, metadata
                )

                async with pool.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute("UPDATE queued_demos SET status = 'completed' WHERE id = %s", (q_id,))
                
                logger.info(f"Demo {demo_id} finished.")
                return True

            except Exception as e:
                logger.exception(f"Failed to process upload {q_id}: {e}")
                async with pool.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute(
                            "UPDATE queued_demos SET status = 'failed', error_message = %s WHERE id = %s", 
                            (str(e), q_id)
                        )
            finally:
                if scheduler: scheduler.resume()
                # Force a collection between demos. A single parsed demo can
                # leave hundreds of MB of pandas/numpy buffers in gen2 even after
                # every local reference is dropped; without an explicit pass
                # the next demo in the queue piles on top of it (the reported
                # 10 GB-after-a-few-demos symptom).
                gc.collect()
        
        return False

    async def check_and_process_enrichment(self, scheduler=None) -> bool:
        """
        Poll demo_enrichment_jobs and stamp callouts onto curated rounds.

        Used when an admin promotes an already-parsed demo to an admin demo:
        the .dem chunks are gone, so we enrich the stored gzipped frame_data in
        place (gunzip -> stamp place/from via map_callout_zones -> gzip).
        """
        from enrichment import enrich_record
        from post_processor import _load_zones_for_map

        pool = await self.get_pool()

        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                await cursor.execute(
                    "SELECT id, demo_id, round_indices FROM demo_enrichment_jobs "
                    "WHERE status = 'pending' LIMIT 1"
                )
                job = await cursor.fetchone()

        if not job:
            return False

        job_id = job["id"]
        demo_id = job["demo_id"]
        round_indices = job["round_indices"]
        if isinstance(round_indices, str):
            round_indices = json.loads(round_indices)

        if scheduler:
            scheduler.pause()

        try:
            async with pool.acquire() as conn:
                async with conn.cursor() as cursor:
                    await cursor.execute(
                        "UPDATE demo_enrichment_jobs SET status = 'processing' WHERE id = %s",
                        (job_id,),
                    )

            # Resolve the demo's map, then load its callout zones once.
            async with pool.acquire() as conn:
                async with conn.cursor(aiomysql.DictCursor) as cursor:
                    await cursor.execute("SELECT map FROM demos WHERE id = %s", (demo_id,))
                    demo_row = await cursor.fetchone()

            map_name = demo_row["map"] if demo_row else None
            zones = await _load_zones_for_map(pool, map_name) if map_name else []
            if not zones:
                logger.warning(
                    f"[Enrichment] No callout zones for map '{map_name}' (demo {demo_id}); "
                    "rounds will be marked curated but carry no callout labels."
                )

            cache: dict = {}
            for round_index in round_indices:
                async with pool.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute(
                            "SELECT frame_data FROM demo_rounds WHERE demo_id = %s AND round_index = %s",
                            (demo_id, round_index),
                        )
                        row = await cursor.fetchone()

                if not row or row[0] is None:
                    logger.warning(f"[Enrichment] demo {demo_id} round {round_index} has no frame_data; skipping")
                    continue

                record = json.loads(gzip.decompress(row[0]).decode("utf-8"))
                enrich_record(record, zones, cache)
                blob = gzip.compress(json.dumps(record, ensure_ascii=False).encode("utf-8"), compresslevel=1)

                async with pool.acquire() as conn:
                    async with conn.cursor() as cursor:
                        await cursor.execute(
                            "UPDATE demo_rounds SET frame_data = %s, curated = 1 "
                            "WHERE demo_id = %s AND round_index = %s",
                            (blob, demo_id, round_index),
                        )

            async with pool.acquire() as conn:
                async with conn.cursor() as cursor:
                    await cursor.execute(
                        "UPDATE demo_enrichment_jobs SET status = 'completed' WHERE id = %s",
                        (job_id,),
                    )
            logger.info(f"[Enrichment] demo {demo_id} rounds {round_indices} enriched")
            return True

        except Exception as e:
            logger.exception(f"[Enrichment] job {job_id} (demo {demo_id}) failed: {e}")
            async with pool.acquire() as conn:
                async with conn.cursor() as cursor:
                    await cursor.execute(
                        "UPDATE demo_enrichment_jobs SET status = 'failed', error_message = %s WHERE id = %s",
                        (str(e), job_id),
                    )
            return False
        finally:
            if scheduler:
                scheduler.resume()

    async def cleanup_chunks(self) -> None:
        pool = await self.get_pool()
        try:
            async with pool.acquire() as conn:
                async with conn.cursor() as cursor:
                    await cursor.execute("SELECT id FROM queued_demos WHERE status IN ('failed', 'completed') LIMIT 10")
                    chunks = await cursor.fetchall()
                    for (q_id,) in chunks:
                        await cursor.execute("DELETE FROM queued_demo_chunks WHERE queued_demo_id = %s", (q_id,))
                    await conn.commit()
        except Exception as e:
            logger.error(f"Cleanup error: {e}")