import { exec } from 'node:child_process';
import { promisify } from 'node:util';
import fs from 'node:fs';
import readline from 'node:readline';

const execAsync = promisify(exec);
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

async function runLaravelVueDoctor() {
  console.log('🩺 Starting Full-Stack Laravel + Vue Doctor Diagnosis...\n');
  let score = 100;
  let issues = [];

  // FRONTEND: Vue & JS/TS
  // try {
  //   console.log('Scanning Vue frontend (resources/js) for anti-patterns and 4-space indentation...');
  //   const eslintCmd = `npx eslint "./resources/js/**/*.{js,ts,vue}" --format json`;
  //   const { stdout: eslintOut } = await execAsync(eslintCmd);
  //   parseEslint(eslintOut, issues);
  // } catch (error) {
  //   if (error.stdout) {
  //     parseEslint(error.stdout, issues);
  //   } else {
  //     issues.push(`[Frontend Lint] Could not run ESLint. Error: ${error.message}`);
  //     score -= 10;
  //   }
  // }

  // try {
  //   console.log('Verifying Vue template correctness and architecture...');
  //   await execAsync('npx vue-tsc --noEmit');
  // } catch (error) {
  //   if (error.stdout) {
  //     const lines = error.stdout.split('\n').filter(l => l.includes('error TS') || l.includes('error Vue'));
  //     lines.forEach(line => {
  //       issues.push(`[Vue/TypeScript] ${line.trim()}`);
  //       score -= 1.5;
  //     });
  //   }
  // }

  // BACKEND: Laravel Pint (Style/Formatting)
  // try {
  //   console.log('Checking PHP code style and formatting (Laravel Pint)...');
  //   if (fs.existsSync('./vendor/bin/pint')) {
  //     await execAsync('./vendor/bin/pint --test --format=json');
  //   } else {
  //     issues.push('[Backend Style] Laravel Pint is not installed. Run "composer require laravel/pint --dev".');
  //     score -= 5;
  //   }
  // } catch (error) {
  //   if (error.stdout) {
  //     try {
  //       const pintResults = JSON.parse(error.stdout);
  //       if (pintResults.files) {
  //         pintResults.files.forEach(file => {
  //           const fileName = file.name.replace(process.cwd(), '').replace(/^[\/\\]/, '');
  //           const fixers = file.appliedFixers ? file.appliedFixers.join(', ') : 'Style errors';
  //           issues.push(`[Backend Style] ${fileName} - ${fixers}`);
  //           score -= 1.5;
  //         });
  //       }
  //     } catch (e) {
  //       issues.push('[Backend Style] PHP code style issues detected by Pint. Run "./vendor/bin/pint --test" for details.');
  //       score -= 10;
  //     }
  //   }
  // }

  // BACKEND: Laravel Routing/Config
  try {
    console.log('Verifying Laravel configuration and route health...');
    await execAsync('php artisan optimize:clear');
  } catch (error) {
    issues.push('[Laravel System] Artisan optimize:clear failed. Check for syntax errors in config or routes.');
    score -= 15;
  }

  // BACKEND: Larastan/PHPStan (Logic/Types/Bugs)
  try {
    console.log('Running Larastan using phpstan.neon for deep PHP bug detection...');
    if (fs.existsSync('./vendor/bin/phpstan')) {
      // Explicitly telling PHPStan to use the configuration file via -c
      await execAsync('./vendor/bin/phpstan analyse -c phpstan.neon --error-format=json');
    } else {
      issues.push('[Backend Logic] Larastan is missing. To catch specific PHP bugs, run: composer require --dev larastan/larastan');
      score -= 10;
    }
  } catch (error) {
    if (error.stdout) {
      try {
        const phpstanResults = JSON.parse(error.stdout);
        if (phpstanResults.files) {
          for (const [filePath, fileData] of Object.entries(phpstanResults.files)) {
            const fileName = filePath.replace(process.cwd(), '').replace(/^[\/\\]/, '');
            fileData.messages.forEach(msg => {
              issues.push(`[Backend Logic] ${fileName}:${msg.line || '?'} - ${msg.message}`);
              score -= 2;
            });
          }
        }
      } catch (e) {
        issues.push('[Backend Logic] Larastan detected architectural or typing issues in your PHP code.');
        score -= 10;
      }
    }
  }

  function parseEslint(output, issueArray) {
    try {
      const results = JSON.parse(output);
      results.forEach(file => {
        file.messages.forEach(msg => {
          const fileName = file.filePath.replace(process.cwd(), '').replace(/^[\/\\]/, '');
          issueArray.push(`[Frontend Lint] ${fileName}:${msg.line} - ${msg.message} (${msg.ruleId})`);
          score -= msg.severity === 2 ? 2 : 0.5;
        });
      });
    } catch (e) {
      // Ignore parse errors
    }
  }

  score = Math.max(0, Math.min(100, Math.round(score)));

  console.log('\n--- 🩺 LARAVEL + VUE DOCTOR DIAGNOSTICS ---');
  if (issues.length === 0) {
    console.log('✅ No issues found! Your full-stack codebase is incredibly healthy.');
    rl.close();
    return;
  } else {
    // Show top 30 issues to prevent terminal flooding
    issues.reverse().slice(0, 50).forEach(issue => console.log(`❌ ${issue}`));
    if (issues.length > 30) {
      console.log(`...and ${issues.length - 30} more hidden issues.`);
    }
  }

  console.log(`\nHEALTH SCORE: ${score}/100`);
  if (score >= 90) {
    console.log('Status: Excellent 🟢');
  } else if (score >= 70) {
    console.log('Status: Needs Work 🟡');
  } else {
    console.log('Status: Critical 🔴 - Manual intervention highly recommended.');
  }

  // Auto-fix prompt
  rl.question('\n🔧 Would you like to automatically fix formatting and linting errors? (y/N): ', async (answer) => {
    if (answer.toLowerCase() === 'y') {
      console.log('\nApplying auto-fixes...');
      
      try {
        console.log('Fixing frontend (ESLint)...');
        const fixEslintCmd = `npx eslint "./resources/js/**/*.{js,ts,vue}" --fix`;
        await execAsync(fixEslintCmd);
      } catch (error) {
        // ESLint exits with error code if some rules are unfixable automatically
      }

      try {
        if (fs.existsSync('./vendor/bin/pint')) {
          console.log('Fixing backend styles (Laravel Pint)...');
          await execAsync('./vendor/bin/pint');
        }
      } catch (error) {
        // Ignore pint exit code warnings
      }

      console.log('✅ Auto-fix complete! Run the script again to see your updated score.');
    } else {
      console.log('Skipping auto-fix.');
    }
    
    rl.close();
  });
}

runLaravelVueDoctor();