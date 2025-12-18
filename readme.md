




# ApiRequest

A simple TCP-based API plugin for PocketMine-MP that allows external services 
or other plugins to execute console commands on your server.

⚠️ **WARNING**  
This is just a simple TCP based API client to run commands on your server.  
**Do NOT use this as-is.** Kindly add your own authorization header or security layer.  
If not protected, **anyone can abuse it**.



## Features

- Lightweight TCP HTTP listener
- Executes console commands via POST requests
- Public API method for other plugins
- Basic crash prevention and input validation
- No dependencies



## Configuration

`config.yml`
```yml
port: 8085
max-request-size: 4096
````

---

## HTTP API Usage

### Endpoint

```
POST http://127.0.0.1:8085
```

### JSON Body

```json
{
  "command": "say Hello from API"
}
```

### Example using curl

```
curl -X POST http://127.0.0.1:8085 \
  -H "Content-Type: application/json" \
  -d '{"command":"say Hello from API"}'
```

### Example Response

```json
{
  "status": "executed",
  "command": "say Hello from API"
}
```

---

## Using the API from Another Plugin

```php
use SilentMussle913\API\ApiRequest;

$api = $this->getServer()->getPluginManager()->getPlugin("ApiRequest");

if ($api instanceof ApiRequest) {
    $api->sendApiCommand("say Hello from another plugin");
}
```

---

## Security Notice

This plugin executes **console commands**.

You should add:

* Authorization headers
* IP whitelisting
* Command whitelisting
* Rate limiting

Failure to do so may allow **unauthorized control of your server**.

---

## License

MIT License
Use at your own risk.

```

---

If you want, I can also:
- Harden the plugin and update README accordingly
- Add a token-based auth example
- Add a command whitelist section
- Write a “Production Usage” warning block

Just tell me.
```
