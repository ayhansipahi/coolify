<?php

namespace App\Livewire\Server\Proxy;

use App\Actions\Proxy\CheckProxy;
use App\Jobs\ContainerStatusJob;
use App\Models\Server;
use Livewire\Component;

class Status extends Component
{
    public Server $server;
    public bool $polling = false;
    public int $numberOfPolls = 0;

    protected $listeners = ['proxyStatusUpdated', 'startProxyPolling'];
    public function mount() {
        $this->checkProxy();
    }
    public function startProxyPolling()
    {
        $this->checkProxy();
    }
    public function proxyStatusUpdated()
    {
        $this->server->refresh();
    }
    public function checkProxy(bool $notification = false)
    {
        try {
            if ($this->polling) {
                if ($this->numberOfPolls >= 10) {
                    $this->polling = false;
                    $this->numberOfPolls = 0;
                    $notification && $this->dispatch('error', 'Proxy is not running.');
                    return;
                }
                $this->numberOfPolls++;
            }
            CheckProxy::run($this->server, true);
            $this->dispatch('proxyStatusUpdated');
            if ($this->server->proxy->status === 'running') {
                $this->polling = false;
                $notification && $this->dispatch('success', 'Proxy is running.');
            } else {
                $notification && $this->dispatch('error', 'Proxy is not running.');
            }
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
    public function getProxyStatus()
    {
        try {
            dispatch_sync(new ContainerStatusJob($this->server));
            $this->dispatch('proxyStatusUpdated');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
