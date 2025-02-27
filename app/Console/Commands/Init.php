<?php

namespace App\Console\Commands;

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\CleanupHelperContainersJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class Init extends Command
{
    protected $signature = 'app:init {--cleanup}';
    protected $description = 'Cleanup instance related stuffs';

    public function handle()
    {
        $this->alive();
        $cleanup = $this->option('cleanup');
        if ($cleanup) {
            echo "Running cleanup\n";
            $this->cleanup_stucked_resources();
            // $this->cleanup_ssh();
        }
        $this->cleanup_in_progress_application_deployments();
        $this->cleanup_stucked_helper_containers();

        try {
            setup_dynamic_configuration();
        } catch (\Throwable $e) {
            echo "Could not setup dynamic configuration: {$e->getMessage()}\n";
        }

        $settings = InstanceSettings::get();
        if (!is_null(env('AUTOUPDATE', null))) {
            if (env('AUTOUPDATE') == true) {
                $settings->update(['is_auto_update_enabled' => true]);
            } else {
                $settings->update(['is_auto_update_enabled' => false]);
            }
        }
    }
    private function cleanup_stucked_helper_containers()
    {
        $servers = Server::all();
        foreach ($servers as $server) {
            if ($server->isFunctional()) {
                CleanupHelperContainersJob::dispatch($server);
            }
        }
    }
    private function alive()
    {
        $id = config('app.id');
        $version = config('version');
        $settings = InstanceSettings::get();
        $do_not_track = data_get($settings, 'do_not_track');
        if ($do_not_track == true) {
            echo "Skipping alive as do_not_track is enabled\n";
            return;
        }
        try {
            Http::get("https://get.coollabs.io/coolify/v4/alive?appId=$id&version=$version");
            echo "I am alive!\n";
        } catch (\Throwable $e) {
            echo "Error in alive: {$e->getMessage()}\n";
        }
    }
    // private function cleanup_ssh()
    // {

    // TODO: it will cleanup id.root@host.docker.internal
    //     try {
    //         $files = Storage::allFiles('ssh/keys');
    //         foreach ($files as $file) {
    //             Storage::delete($file);
    //         }
    //         $files = Storage::allFiles('ssh/mux');
    //         foreach ($files as $file) {
    //             Storage::delete($file);
    //         }
    //     } catch (\Throwable $e) {
    //         echo "Error in cleaning ssh: {$e->getMessage()}\n";
    //     }
    // }
    private function cleanup_in_progress_application_deployments()
    {
        // Cleanup any failed deployments

        try {
            $halted_deployments = ApplicationDeploymentQueue::where('status', '==', ApplicationDeploymentStatus::IN_PROGRESS)->where('status', '==', ApplicationDeploymentStatus::QUEUED)->get();
            foreach ($halted_deployments as $deployment) {
                $deployment->status = ApplicationDeploymentStatus::FAILED->value;
                $deployment->save();
            }
        } catch (\Throwable $e) {
            echo "Error: {$e->getMessage()}\n";
        }
    }
    private function cleanup_stucked_resources()
    {
        // Cleanup any resources that are not attached to any environment or destination or server
        try {
            $applications = Application::all();
            foreach ($applications as $application) {
                if (!data_get($application, 'environment')) {
                    echo 'Application without environment: ' . $application->name . ' soft deleting\n';
                    $application->delete();
                    continue;
                }
                if (!$application->destination()) {
                    echo 'Application without destination: ' . $application->name . ' soft deleting\n';
                    $application->delete();
                    continue;
                }
                if (!data_get($application, 'destination.server')) {
                    echo 'Application without server: ' . $application->name . ' soft deleting\n';
                    $application->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in application: {$e->getMessage()}\n";
        }
        try {
            $postgresqls = StandalonePostgresql::all();
            foreach ($postgresqls as $postgresql) {
                if (!data_get($postgresql, 'environment')) {
                    echo 'Postgresql without environment: ' . $postgresql->name . ' soft deleting\n';
                    $postgresql->delete();
                    continue;
                }
                if (!$postgresql->destination()) {
                    echo 'Postgresql without destination: ' . $postgresql->name . ' soft deleting\n';
                    $postgresql->delete();
                    continue;
                }
                if (!data_get($postgresql, 'destination.server')) {
                    echo 'Postgresql without server: ' . $postgresql->name . ' soft deleting\n';
                    $postgresql->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in postgresql: {$e->getMessage()}\n";
        }
        try {
            $redis = StandaloneRedis::all();
            foreach ($redis as $redis) {
                if (!data_get($redis, 'environment')) {
                    echo 'Redis without environment: ' . $redis->name . ' soft deleting\n';
                    $redis->delete();
                    continue;
                }
                if (!$redis->destination()) {
                    echo 'Redis without destination: ' . $redis->name . ' soft deleting\n';
                    $redis->delete();
                    continue;
                }
                if (!data_get($redis, 'destination.server')) {
                    echo 'Redis without server: ' . $redis->name . ' soft deleting\n';
                    $redis->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in redis: {$e->getMessage()}\n";
        }

        try {
            $mongodbs = StandaloneMongodb::all();
            foreach ($mongodbs as $mongodb) {
                if (!data_get($mongodb, 'environment')) {
                    echo 'Mongodb without environment: ' . $mongodb->name . ' soft deleting\n';
                    $mongodb->delete();
                    continue;
                }
                if (!$mongodb->destination()) {
                    echo 'Mongodb without destination: ' . $mongodb->name . ' soft deleting\n';
                    $mongodb->delete();
                    continue;
                }
                if (!data_get($mongodb, 'destination.server')) {
                    echo 'Mongodb without server:  ' . $mongodb->name . ' soft deleting\n';
                    $mongodb->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in mongodb: {$e->getMessage()}\n";
        }

        try {
            $mysqls = StandaloneMysql::all();
            foreach ($mysqls as $mysql) {
                if (!data_get($mysql, 'environment')) {
                    echo 'Mysql without environment: ' . $mysql->name . ' soft deleting\n';
                    $mysql->delete();
                    continue;
                }
                if (!$mysql->destination()) {
                    echo 'Mysql without destination: ' . $mysql->name . ' soft deleting\n';
                    $mysql->delete();
                    continue;
                }
                if (!data_get($mysql, 'destination.server')) {
                    echo 'Mysql without server: ' . $mysql->name . ' soft deleting\n';
                    $mysql->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in mysql: {$e->getMessage()}\n";
        }

        try {
            $mariadbs = StandaloneMariadb::all();
            foreach ($mariadbs as $mariadb) {
                if (!data_get($mariadb, 'environment')) {
                    echo 'Mariadb without environment: ' . $mariadb->name . ' soft deleting\n';
                    $mariadb->delete();
                    continue;
                }
                if (!$mariadb->destination()) {
                    echo 'Mariadb without destination: ' . $mariadb->name . ' soft deleting\n';
                    $mariadb->delete();
                    continue;
                }
                if (!data_get($mariadb, 'destination.server')) {
                    echo 'Mariadb without server: ' . $mariadb->name . ' soft deleting\n';
                    $mariadb->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in mariadb: {$e->getMessage()}\n";
        }

        try {
            $services = Service::all();
            foreach ($services as $service) {
                if (!data_get($service, 'environment')) {
                    echo 'Service without environment: ' . $service->name . ' soft deleting\n';
                    $service->delete();
                    continue;
                }
                if (!$service->destination()) {
                    echo 'Service without destination: ' . $service->name . ' soft deleting\n';
                    $service->delete();
                    continue;
                }
                if (!data_get($service, 'server')) {
                    echo 'Service without server: ' . $service->name . ' soft deleting\n';
                    $service->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in service: {$e->getMessage()}\n";
        }
        try {
            $serviceApplications = ServiceApplication::all();
            foreach ($serviceApplications as $service) {
                if (!data_get($service, 'service')) {
                    echo 'ServiceApplication without service: ' . $service->name . ' soft deleting\n';
                    $service->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in serviceApplications: {$e->getMessage()}\n";
        }
        try {
            $serviceDatabases = ServiceDatabase::all();
            foreach ($serviceDatabases as $service) {
                if (!data_get($service, 'service')) {
                    echo 'ServiceDatabase without service: ' . $service->name . ' soft deleting\n';
                    $service->delete();
                    continue;
                }
            }
        } catch (\Throwable $e) {
            echo "Error in ServiceDatabases: {$e->getMessage()}\n";
        }
    }
}
