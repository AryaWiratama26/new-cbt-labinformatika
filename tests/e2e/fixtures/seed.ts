import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '../../..');

function run(cmd: string) {
  return execSync(cmd, { cwd: projectRoot, stdio: 'pipe' }).toString();
}

export function seedTestData() {
  run('php artisan migrate:fresh --seed --force');

  run(`php artisan tinker --execute='$u=\\App\\Models\\User::where("role","admin")->first();$u->password=bcrypt("admin");$u->save();echo "ok\\n";'`);

  run(`php artisan tinker --execute='
    \\App\\Models\\Classroom::create(["name"=>"IF-1","academic_year"=>"2025/2026","semester"=>"Ganjil"]);
    \\App\\Models\\User::create(["username"=>"20241001","name"=>"Test Student","role"=>"mahasiswa","classroom_id"=>1,"password"=>bcrypt("test123")]);
    $c=\\App\\Models\\Course::create(["code"=>"PW","name"=>"Pemrograman Web"]);
    $m=$c->modules()->create(["name"=>"Modul 1","module_number"=>"Modul 1","description"=>"Test"]);
    $q=$m->questions()->create(["content"=>"Test question?","category"=>"mudah"]);
    $q->options()->createMany([["content"=>"Answer A","is_correct"=>true],["content"=>"Answer B","is_correct"=>false],["content"=>"Answer C","is_correct"=>false],["content"=>"Answer D","is_correct"=>false]]);
    echo "ok\\n";
  '`);
}
