<?php
namespace Deployer;

require 'recipe/laravel.php';

// Project name
set('application', 'basic_deploy');

// Project repository
set('repository', 'git@github.com:trunghq-2593/basic_deploy.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', false);

// Shared files/dirs between deploys
add('shared_files', ['.env']);
add('shared_dirs', ['storage']);

// Writable dirs by web server
add('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

set('allow_anonymous_stats', false);

/**
 * npm task
 */

set('bin/npm', function () {
    return run("command -v 'npm' || which 'npm' || type -p 'npm'");
});

//set('composer_options', 'install --verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader');


// Hosts

host('dev')
    ->hostname('18.222.52.87')
    ->user('deploy')
    ->stage('dev')
    ->set('deploy_path', '/home/deploy/{{application}}')
    ->set('branch', 'dev');

// Tasks


// Run npm install

desc('Install packages');
task('npm:install', function () {
    if (has('previous_release')) {
        if (test ('[ -d {{previous_release}}/node_modules]')) {
            run('cp -R {{previous_release}}/node_modules {{release_path}}');
        }
    }

    run('cd {{release_path}} && {{bin/npm}} install');
});

// Override deploy:lock, change from if app is locked throw exception to unlock app
task('deploy:lock', function () {
    $locked = test('[ -f {{deploy_path}}/.dep/deploy.lock ]');

    if ($locked) {
        run('rm -f {{deploy_path}}/.dep/deploy.lock');
    }

    run('touch {{deploy_path}}/.dep/deploy.lock');
});

task('reload:php-fpm', function () {
    run('sudo /usr/sbin/service php7.4-fpm reload');
});

task('npm:run_dev', function () {
    run('cd {{release_path}} && {{bin/npm}} run development');
});

task('setup-laravel', function () {
    run('cd {{release_path}} && cp .env.example {{deploy_path}}/shared/.env');
    run('cd {{release_path}} && php artisan key:generate');
});

task('init-project', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'setup-laravel',
    'deploy:symlink',
    'deploy:unlock',
]);

task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'npm:install',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'npm:run_dev',
    'artisan:storage:link',
    'artisan:view:cache',
    'artisan:config:cache',
    'artisan:optimize',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'reload:php-fpm',
    'artisan:migrate',
]);

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

after('deploy', 'success');
// Migrate database before symlink new release.

before('deploy:symlink', 'artisan:migrate');

