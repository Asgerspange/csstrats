import axios from 'axios'
import router from '@/router' // Adjust path to your router
import { useUpgradeModal } from '@/composables/useUpgradeModal'
import { AppErrorCode } from '@/constants/errorCodes'

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    withCredentials: true,
})

api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Check for our custom Laravel Middleware response
        if (error.response?.status === 403 && error.response.data?.app_code) {
            const appCode = error.response.data.app_code;

            // Plan-limit-exceeded pops the in-app upgrade modal instead of
            // shipping the user off to a full access-denied page. The
            // backend sends `feature` + `required_tier` + `limit` so the
            // modal can tailor its copy. We still reject the error so the
            // calling code's own catch runs (e.g. a form button resets).
            if (appCode === AppErrorCode.PLAN_LIMIT_EXCEEDED) {
                const { open } = useUpgradeModal()
                open({
                    feature: error.response.data.feature ?? 'stratbooks',
                    requiredTier: error.response.data.required_tier ?? null,
                    limit: error.response.data.limit ?? null,
                })
                return Promise.reject(error)
            }

            // Map access denied (707) or edit denied (708): pop the upgrade
            // modal instead of redirecting to access-denied. The user is on
            // a locked map and needs the upgrade path, not a dead-end page.
            if (appCode === AppErrorCode.NO_MAP_ACCESS || appCode === AppErrorCode.NO_MAP_EDIT_ACCESS) {
                const { open } = useUpgradeModal()
                open({
                    feature: 'stratbooks',
                    requiredTier: 'Team',
                })
                return Promise.reject(error)
            }

            // Redirect to the error page with the code
            router.push({
                name: 'access-denied',
                query: { code: appCode }
            });
        }
        return Promise.reject(error);
    }
)

export default api