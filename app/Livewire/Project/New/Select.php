<?php

namespace App\Livewire\Project\New;

use App\Models\Project;
use App\Models\Server;
use Countable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Select extends Component
{
    public $current_step = 'type';
    public ?int $server = null;
    public string $type;
    public string $server_id;
    public string $destination_uuid;
    public Countable|array|Server $servers = [];
    public Collection|array $standaloneDockers = [];
    public Collection|array $swarmDockers = [];
    public array $parameters;
    public Collection|array $services = [];
    public Collection|array $allServices = [];

    public bool $loadingServices = true;
    public bool $loading = false;
    public $environments = [];
    public ?string $selectedEnvironment = null;
    public ?string $existingPostgresqlUrl = null;

    public ?string $search = null;
    protected $queryString = [
        'server',
        'search'
    ];

    public function mount()
    {
        $this->parameters = get_route_parameters();
        if (isDev()) {
            $this->existingPostgresqlUrl = 'postgres://coolify:password@coolify-db:5432';
        }
        $projectUuid = data_get($this->parameters, 'project_uuid');
        $this->environments = Project::whereUuid($projectUuid)->first()->environments;
        $this->selectedEnvironment = data_get($this->parameters, 'environment_name');
    }
    public function render()
    {
        $this->loadServices();
        return view('livewire.project.new.select');
    }

    public function updatedSelectedEnvironment()
    {
        return $this->redirectRoute('project.resources.new', [
            'project_uuid' => $this->parameters['project_uuid'],
            'environment_name' => $this->selectedEnvironment,
        ], navigate: true);
    }

    // public function addExistingPostgresql()
    // {
    //     try {
    //         instantCommand("psql {$this->existingPostgresqlUrl} -c 'SELECT 1'");
    //         $this->dispatch('success', 'Successfully connected to the database.');
    //     } catch (\Throwable $e) {
    //         return handleError($e, $this);
    //     }
    // }

    public function loadServices()
    {
        try {
            if (count($this->allServices) > 0) {
                if (!$this->search) {
                    $this->services = $this->allServices;
                    return;
                }
                $this->services = $this->allServices->filter(function ($service, $key) {
                    $tags = collect(data_get($service, 'tags', []));
                    return str_contains(strtolower($key), strtolower($this->search)) || $tags->contains(function ($tag) {
                        return str_contains(strtolower($tag), strtolower($this->search));
                    });
                });
            } else {
                $this->search = null;
                $this->allServices = getServiceTemplates();
                $this->services = $this->allServices->filter(function ($service, $key) {
                    return str_contains(strtolower($key), strtolower($this->search));
                });;
                $this->dispatch('success', 'Successfully loaded services.');
            }
        } catch (\Throwable $e) {
            return handleError($e, $this);
        } finally {
            $this->loadingServices = false;
        }
    }
    public function setType(string $type)
    {
        $this->type = $type;
        if ($this->loading) return;
        $this->loading = true;
        if ($type === "existing-postgresql") {
            $this->current_step = $type;
            return;
        }
        if (count($this->servers) === 1) {
            $server = $this->servers->first();
            $this->setServer($server);
        }
        if (!is_null($this->server)) {
            $foundServer = $this->servers->where('id', $this->server)->first();
            if ($foundServer) {
                return $this->setServer($foundServer);
            }
        }
        $this->current_step = 'servers';
    }

    public function setServer(Server $server)
    {
        $this->server_id = $server->id;
        $this->standaloneDockers = $server->standaloneDockers;
        $this->swarmDockers = $server->swarmDockers;
        $this->current_step = 'destinations';
    }

    public function setDestination(string $destination_uuid)
    {
        $this->destination_uuid = $destination_uuid;
        return redirect()->route('project.resources.new', [
            'project_uuid' => $this->parameters['project_uuid'],
            'environment_name' => $this->parameters['environment_name'],
            'type' => $this->type,
            'destination' => $this->destination_uuid,
            'server_id' => $this->server_id,
        ]);
    }

    public function loadServers()
    {
        $this->servers = Server::isUsable()->get();
    }
}
