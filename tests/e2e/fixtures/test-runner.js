const { execSync } = require('child_process');
const cmd = process.argv.slice(2).join(' ');
try {
  const r = execSync(cmd, { cwd: process.cwd(), shell: true, stdio: 'pipe' });
  console.log('STDOUT:', r.toString().trim());
} catch (e) {
  console.log('FAILED:', e.message);
  if (e.stdout) console.log('STDOUT:', e.stdout.toString());
  if (e.stderr) console.log('STDERR:', e.stderr.toString());
}
