<?php

namespace App\Http\Livewire;

use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use Livewire\Component;

class Boarding extends Component
{

    public string $currentState = 'create-private-key';
    // public ?string $serverType = null;

    public ?string $privateKeyType = null;
    public ?string $privateKey = null;
    public ?string $privateKeyName = null;
    public ?string $privateKeyDescription = null;
    public ?PrivateKey $createdPrivateKey = null;

    public ?string $remoteServerName = null;
    public ?string $remoteServerDescription = null;
    public ?string $remoteServerHost = null;
    public ?int    $remoteServerPort = 22;
    public ?string $remoteServerUser = 'root';
    public ?Server $createdServer = null;

    public function mount()
    {
        $this->privateKeyName = generate_random_name();
        $this->remoteServerName = generate_random_name();
        if (is_dev()) {
            $this->privateKey = '-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevAAAAJi/QySHv0Mk
hwAAAAtzc2gtZWQyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevA
AAAECBQw4jg1WRT2IGHMncCiZhURCts2s24HoDS0thHnnRKVuGmoeGq/pojrsyP1pszcNV
uZx9iFkCELtxrh31QJ68AAAAEXNhaWxANzZmZjY2ZDJlMmRkAQIDBA==
-----END OPENSSH PRIVATE KEY-----';
            $this->privateKeyDescription = 'Created by Coolify';
            $this->remoteServerDescription = 'Created by Coolify';
            $this->remoteServerHost = 'coolify-testing-host';
        }
    }
    public function restartBoarding()
    {
        if ($this->createdServer) {
            $this->createdServer->delete();
        }
        if ($this->createdPrivateKey) {
            $this->createdPrivateKey->delete();
        }
        return redirect()->route('boarding');
    }
    public function skipBoarding()
    {
        currentTeam()->update([
            'show_boarding' => false
        ]);
        refreshSession();
        return redirect()->route('dashboard');
    }
    public function setServer(string $type)
    {
        if ($type === 'localhost') {
            $this->currentState = 'create-project';
        } elseif ($type === 'remote') {
            $this->currentState = 'private-key';
        }
    }
    public function setPrivateKey(string $type)
    {
        $this->privateKeyType = $type;
        $this->currentState = 'create-private-key';
    }
    public function savePrivateKey()
    {
        $this->validate([
            'privateKeyName' => 'required',
            'privateKey' => 'required',
        ]);
        $this->currentState = 'create-server';
    }
    public function saveServer()
    {
        $this->validate([
            'remoteServerName' => 'required',
            'remoteServerHost' => 'required',
            'remoteServerPort' => 'required',
            'remoteServerUser' => 'required',
        ]);
        if ($this->privateKeyType === 'create') {
            $this->createNewPrivateKey();
        }
        $this->privateKey = formatPrivateKey($this->privateKey);
        $this->createdPrivateKey = PrivateKey::create([
            'name' => $this->privateKeyName,
            'description' => $this->privateKeyDescription,
            'private_key' => $this->privateKey,
            'team_id' => currentTeam()->id
        ]);
        $this->createdServer = Server::create([
            'name' => $this->remoteServerName,
            'ip' => $this->remoteServerHost,
            'port' => $this->remoteServerPort,
            'user' => $this->remoteServerUser,
            'description' => $this->remoteServerDescription,
            'private_key_id' => $this->createdPrivateKey->id,
            'team_id' => currentTeam()->id
        ]);
        try {
            ['uptime' => $uptime, 'dockerVersion' => $dockerVersion] = validateServer($this->createdServer);
            if (!$uptime) {
                $this->createdServer->delete();
                $this->createdPrivateKey->delete();
                throw new \Exception('Server is not reachable.');
            } else {
                $this->createdServer->settings->update([
                    'is_reachable' => true,
                ]);
                $this->emit('success', 'Server is reachable.');
            }
            if ($dockerVersion) {
                $this->emit('error', 'Docker is not installed on the server.');
                $this->currentState = 'install-docker';
                return;
            }
            ray($uptime, $dockerVersion);
        } catch (\Exception $e) {
            return general_error_handler(customErrorMessage: "Server is not reachable. Reason: {$e->getMessage()}", that: $this);
        }
    }
    private function createNewPrivateKey()
    {
        $this->privateKeyName = generate_random_name();
        $this->privateKeyDescription = 'Created by Coolify';
        $this->privateKey = generateSSHKey();
    }
    public function createNewProject()
    {
        Project::create([
            'name' => generate_random_name(),
            'team_id' => currentTeam()->id
        ]);
    }
}
