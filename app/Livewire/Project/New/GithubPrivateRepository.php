<?php

namespace App\Livewire\Project\New;

use App\Models\Application;
use App\Models\GithubApp;
use App\Models\Project;
use App\Models\StandaloneDocker;
use App\Models\SwarmDocker;
use App\Traits\SaveFromRedirect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class GithubPrivateRepository extends Component
{
    use SaveFromRedirect;
    public $current_step = 'github_apps';
    public $github_apps;
    public GithubApp $github_app;
    public $parameters;
    public $currentRoute;
    public $query;
    public $type;

    public int $selected_repository_id;
    public int $selected_github_app_id;
    public string $selected_repository_owner;
    public string $selected_repository_repo;

    public string $selected_branch_name = 'main';

    public string $token;
    public $repositories;
    public int $total_repositories_count = 0;
    public $branches;
    public int $total_branches_count = 0;
    public int $port = 3000;
    public bool $is_static = false;
    public string|null $publish_directory = null;
    protected int $page = 1;


    public function mount()
    {
        $this->currentRoute = Route::currentRouteName();
        $this->parameters = get_route_parameters();
        $this->query = request()->query();
        $this->repositories = $this->branches = collect();
        $this->github_apps = GithubApp::private();
    }
    public function loadRepositories($github_app_id)
    {
        $this->repositories = collect();
        $this->page = 1;
        $this->selected_github_app_id = $github_app_id;
        $this->github_app = GithubApp::where('id', $github_app_id)->first();
        $this->token = generate_github_installation_token($this->github_app);
        $this->loadRepositoryByPage();
        if ($this->repositories->count() < $this->total_repositories_count) {
            while ($this->repositories->count() < $this->total_repositories_count) {
                $this->page++;
                $this->loadRepositoryByPage();
            }
        }
        $this->selected_repository_id = $this->repositories[0]['id'];
        $this->current_step = 'repository';
    }

    protected function loadRepositoryByPage()
    {
        $response = Http::withToken($this->token)->get("{$this->github_app->api_url}/installation/repositories?per_page=100&page={$this->page}");
        $json = $response->json();
        if ($response->status() !== 200) {
            return $this->dispatch('error', $json['message']);
        }

        if ($json['total_count'] === 0) {
            return;
        }
        $this->total_repositories_count = $json['total_count'];
        $this->repositories = $this->repositories->concat(collect($json['repositories']));
    }

    public function loadBranches()
    {
        $this->selected_repository_owner = $this->repositories->where('id', $this->selected_repository_id)->first()['owner']['login'];
        $this->selected_repository_repo = $this->repositories->where('id', $this->selected_repository_id)->first()['name'];
        $this->branches = collect();
        $this->page = 1;
        $this->loadBranchByPage();
        if ($this->total_branches_count === 100) {
            while ($this->total_branches_count === 100) {
                $this->page++;
                $this->loadBranchByPage();
            }
        }
        $this->selected_branch_name = data_get($this->branches,'0.name');
    }

    protected function loadBranchByPage()
    {
        ray('Loading page ' . $this->page);
        $response = Http::withToken($this->token)->get("{$this->github_app->api_url}/repos/{$this->selected_repository_owner}/{$this->selected_repository_repo}/branches?per_page=100&page={$this->page}");
        $json = $response->json();
        if ($response->status() !== 200) {
            return $this->dispatch('error', $json['message']);
        }

        $this->total_branches_count = count($json);
        $this->branches = $this->branches->concat(collect($json));
    }

    public function submit()
    {
        try {
            $destination_uuid = $this->query['destination'];
            $destination = StandaloneDocker::where('uuid', $destination_uuid)->first();
            if (!$destination) {
                $destination = SwarmDocker::where('uuid', $destination_uuid)->first();
            }
            if (!$destination) {
                throw new \Exception('Destination not found. What?!');
            }
            $destination_class = $destination->getMorphClass();


            $project = Project::where('uuid', $this->parameters['project_uuid'])->first();
            $environment = $project->load(['environments'])->environments->where('name', $this->parameters['environment_name'])->first();

            $application = Application::create([
                'name' => generate_application_name($this->selected_repository_owner . '/' . $this->selected_repository_repo, $this->selected_branch_name),
                'repository_project_id' => $this->selected_repository_id,
                'git_repository' => "{$this->selected_repository_owner}/{$this->selected_repository_repo}",
                'git_branch' => $this->selected_branch_name,
                'build_pack' => 'nixpacks',
                'ports_exposes' => $this->port,
                'publish_directory' => $this->publish_directory,
                'environment_id' => $environment->id,
                'destination_id' => $destination->id,
                'destination_type' => $destination_class,
                'source_id' => $this->github_app->id,
                'source_type' => $this->github_app->getMorphClass()
            ]);
            $application->settings->is_static = $this->is_static;
            $application->settings->save();

            $fqdn = generateFqdn($destination->server, $application->uuid);
            $application->fqdn = $fqdn;

            $application->name = generate_application_name($this->selected_repository_owner . '/' . $this->selected_repository_repo, $this->selected_branch_name, $application->uuid);
            $application->save();

            return $this->redirectRoute('project.application.configuration', [
                'application_uuid' => $application->uuid,
                'environment_name' => $environment->name,
                'project_uuid' => $project->uuid,
            ], navigate: true);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function instantSave()
    {
        if ($this->is_static) {
            $this->port = 80;
            $this->publish_directory = '/dist';
        } else {
            $this->port = 3000;
            $this->publish_directory = null;
        }
        $this->dispatch('success', 'Application settings updated!');
    }
}
