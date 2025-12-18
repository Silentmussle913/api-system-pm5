<?php

declare(strict_types=1);

namespace SilentMussle913\API;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\utils\TextFormat as TF;

class ApiRequest extends PluginBase {

    private $socket = null;
    private bool $running = false;
    private int $port = 8085;
    private int $maxRequestSize = 4096;

    public function onEnable(): void {
        try {
            $this->saveDefaultConfig();
            $config = $this->getConfig();
            $this->port = max(1, (int) $config->get("port", 8085));
            $this->maxRequestSize = max(512, (int) $config->get("max-request-size", 4096));
            $this->startHttpServer();
            $this->getLogger()->info(TF::GREEN . "ApiRequest listening on port {$this->port}");
        } catch (\Throwable $e) {
            $this->getLogger()->critical("Startup failure: {$e->getMessage()}");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

    public function onDisable(): void {
        $this->stopHttpServer();
        $this->getLogger()->info(TF::RED . "ApiRequest stopped");
    }

    private function startHttpServer(): void {
        if ($this->running) {
            return;
        }

        $this->running = true;

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            if (!$this->running) {
                return;
            }

            try {
                if ($this->socket === null) {
                    $this->socket = @stream_socket_server(
                        "tcp://0.0.0.0:{$this->port}",
                        $errno,
                        $errstr,
                        STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
                    );

                    if (!$this->socket) {
                        $this->getLogger()->error("Socket error {$errno}: {$errstr}");
                        $this->running = false;
                        return;
                    }

                    stream_set_blocking($this->socket, false);
                }

                $client = @stream_socket_accept($this->socket, 0);
                if ($client === false) {
                    return;
                }

                $raw = @fread($client, $this->maxRequestSize);
                if ($raw === false || $raw === '') {
                    fclose($client);
                    return;
                }

                $this->handleHttpRequest($client, $raw);
                fclose($client);
            } catch (\Throwable $e) {
                $this->getLogger()->error("Runtime error: {$e->getMessage()}");
            }
        }), 1);
    }

    private function stopHttpServer(): void {
        $this->running = false;
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }

    private function handleHttpRequest($client, string $raw): void {
        $parts = explode("\r\n\r\n", $raw, 2);
        if (count($parts) !== 2) {
            $this->sendResponse($client, 400, "Bad Request", ["error" => "Malformed request"]);
            return;
        }

        [$headers, $body] = $parts;
        $lines = explode("\r\n", $headers);
        $requestLine = $lines[0] ?? '';

        if (!str_starts_with($requestLine, 'POST ')) {
            $this->sendResponse($client, 405, "Method Not Allowed", ["error" => "POST required"]);
            return;
        }

        $data = json_decode(trim($body), true);
        if (!is_array($data)) {
            $this->sendResponse($client, 400, "Bad Request", ["error" => "Invalid JSON"]);
            return;
        }

        if (!isset($data['command']) || !is_string($data['command'])) {
            $this->sendResponse($client, 422, "Unprocessable Entity", ["error" => "Missing command"]);
            return;
        }

        $command = trim($data['command']);
        if ($command === '') {
            $this->sendResponse($client, 422, "Unprocessable Entity", ["error" => "Empty command"]);
            return;
        }

        $this->executeCommand($command);

        $this->sendResponse($client, 200, "OK", [
            "status" => "executed",
            "command" => $command
        ]);
    }

    private function sendResponse($client, int $code, string $message, array $data): void {
        try {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES);
            $out = implode("\r\n", [
                "HTTP/1.1 {$code} {$message}",
                "Content-Type: application/json",
                "Content-Length: " . strlen($json),
                "Connection: close",
                "",
                $json
            ]);
            @fwrite($client, $out);
        } catch (\Throwable) {
        }
    }

    private function executeCommand(string $command): void {
        try {
            $sender = new ConsoleCommandSender(
                $this->getServer(),
                $this->getServer()->getLanguage()
            );
            $this->getServer()->dispatchCommand($sender, $command);
            $this->getLogger()->info("[API] Executed: /{$command}");
        } catch (\Throwable $e) {
            $this->getLogger()->error("Command error: {$e->getMessage()}");
        }
    }

    public function sendApiCommand(string $command): bool {
        if (!$this->isEnabled()) {
            return false;
        }
        $command = trim($command);
        if ($command === '') {
            return false;
        }
        $this->executeCommand($command);
        return true;
    }
}
