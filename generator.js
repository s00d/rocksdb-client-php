const fs = require('fs');
const Handlebars = require('handlebars');
const _ = require('lodash');

// Объект для конвертации типов
const typeConversion = {
  usize: 'int',
  u32: 'int',
  bool: 'bool',
  string: 'string',
};

// Функция для конвертации типов
const convertType = (type) => {
  return typeConversion[type] || type;
};

// Функция для преобразования переносов строк
const convertNewlines = (text) => {
  return text ? text.replace(/\\n/g, '\n     * ') : '';
};

// Рекурсивная функция для обработки параметров с properties
const processParameters = (params, parentKey = '') => {
  const result = [];
  for (const key in params) {
    const param = params[key];
    const paramName = parentKey ? `${parentKey}.${key}` : key;
    if (param.param_type === 'object' && param.properties) {
      result.push(...processParameters(param.properties, paramName));
    } else {
      result.push({
        name: paramName,
        param_type: convertType(param.param_type.toLowerCase()),
        description: param.description,
        required: param.required,
      });
    }
  }
  return result;
};

// Шаблон для генерации методов класса
const methodTemplate = `
    /**
     * {{{description}}}
     *
     * {{#each parameters}}@param {{param_type}} \${{replace name 'options.' ''}} {{description}}
     * {{/each}}
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function {{actionCamel}}({{{parametersList}}}) {
        $request = [
            'action' => '{{action}}',
            'options' => [],
        ];

        {{#each requiredParameters}}
        {{#if (contains name "options.")}}
        $request['options']['{{replace name 'options.' ''}}'] = \${{replace name 'options.' ''}};
        {{else}}
        $request['{{replace name 'options.' ''}}'] = \${{replace name 'options.' ''}};
        {{/if}}
        {{/each}}

        {{#each optionalParameters}}
        if (\${{replace name 'options.' ''}} !== null) {
            {{#if (contains name "options.")}}
            $request['options']['{{replace name 'options.' ''}}'] = \${{replace name 'options.' ''}};
            {{else}}
            $request['{{replace name 'options.' ''}}'] = \${{replace name 'options.' ''}};
            {{/if}}
        }
        {{/each}}

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }
`;

// Регистрируем кастомный хелпер для проверки вхождения строки
Handlebars.registerHelper('contains', (haystack, needle) => haystack.includes(needle));

// Регистрируем кастомный хелпер для замены части строки
Handlebars.registerHelper('replace', (haystack, needle, replacement) => haystack.replace(needle, replacement));

// Чтение JSON-данных
const data = JSON.parse(fs.readFileSync('requests_schema.json', 'utf8'));

// Генерация методов на основе JSON
const generateMethods = (requests) => {
  return requests.map(request => {
    const allParameters = processParameters(request.parameters);

    const parametersList = allParameters.map(param => {
      const defaultValue = param.required ? '' : ' = null';
      return `${param.param_type} $${param.name.replace('options.', '')}${defaultValue}`;
    }).join(', ');

    const methodData = {
      action: request.action,
      actionCamel: _.camelCase(request.action),
      description: convertNewlines(request.description),
      parameters: allParameters,
      requiredParameters: allParameters.filter(param => param.required),
      optionalParameters: allParameters.filter(param => !param.required),
      parametersList,
    };

    const template = Handlebars.compile(methodTemplate);
    return template(methodData);
  }).join('\n');
};

// Основной шаблон класса
const classTemplate = `<?php

namespace s00d\\RocksDB;

use Exception;

class RocksDBClient {
    private string $host;
    private int $port;
    private ?string $token;
    private $socket;
    private int $timeout;
    private int $retryInterval;

    /**
     * Constructor to initialize the RocksDB client.
     *
     * @param string $host The host of the RocksDB server.
     * @param int $port The port of the RocksDB server.
     * @param string|null $token Optional authentication token for the RocksDB server.
     */
    public function __construct(string $host, int $port, string $token = null, int $timeout = 20, int $retryInterval = 2) {
        $this->host = $host;
        $this->port = $port;
        $this->token = $token;
        $this->timeout = $timeout;
        $this->retryInterval = $retryInterval;
    }

    /**
     * Connects to the RocksDB server with retry mechanism.
     *
     * @throws Exception If unable to connect to the server.
     */
    private function connect() {
        $startTime = time();

        while (true) {
            $this->socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 30);

            if ($this->socket) {
                return; // Connection successful
            }

            if (time() - $startTime >= $this->timeout) {
                throw new Exception("Unable to connect to server: $errstr ($errno)");
            }

            // Wait for the retry interval before trying again
            sleep($this->retryInterval);
        }
    }

    /**
     * Sends a request to the RocksDB server.
     *
     * @param array $request The request to be sent.
     * @return array The response from the server.
     * @throws Exception If the response from the server is invalid.
     */
    private function sendRequest(array $request): array {
        if (!$this->socket) {
            $this->connect();
        }

        if ($this->token !== null) {
            $request['token'] = $this->token; // Add token to request if present
        }

        $requestJson = json_encode($request, JSON_THROW_ON_ERROR) . "\\n";
        fwrite($this->socket, $requestJson);

        $responseJson = '';
        while (!feof($this->socket)) {
            $responseJson .= fgets($this->socket);
            if (strpos($responseJson, "\\n") !== false) {
                break;
            }
        }

        $response = json_decode($responseJson, true, 512, JSON_THROW_ON_ERROR);

        if ($response === null) {
            throw new Exception("Invalid response from server");
        }

        return $response;
    }

    /**
     * Handles the response from the server.
     *
     * @param array $response The response from the server.
     * @return mixed The result from the response.
     * @throws Exception If the response indicates an error.
     */
    private function handleResponse(array $response) {
        if ($response['success']) {
            return $response['result'];
        }

        throw new \\RuntimeException($response['error']);
    }

    {{{methods}}}
}
`;

// Генерация методов
const methods = generateMethods(data.requests);

// Генерация полного кода класса
const template = Handlebars.compile(classTemplate);
const classCode = template({ methods });

// Запись в файл
fs.writeFileSync('src/RocksDBClient.php', classCode);

console.log('PHP code generated successfully.');
