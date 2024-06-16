<?php

namespace s00d\RocksDB;

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
        
        if(isset($request['options']) && empty($request['options'])) {
            unset($request['options']);
        }

        $requestJson = json_encode($request, JSON_THROW_ON_ERROR) . "\n";
        fwrite($this->socket, $requestJson);

        $responseJson = '';
        while (!feof($this->socket)) {
            $responseJson .= fgets($this->socket);
            if (strpos($responseJson, "\n") !== false) {
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

        throw new \RuntimeException($response['error']);
    }

    
    /**
     * Inserts a key-value pair into the database.
     * This function handles the `put` action which inserts a specified key-value pair into the RocksDB database.
     * The function can optionally operate within a specified column family and transaction if provided.
     *
     * @param string $key The key to put
     * @param string $value The value to put
     * @param string $cf_name The column family name
     * @param int $txn_id The transaction ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function put(string $key, string $value, string $cf_name = null, int $txn_id = null) {
        $request = [
            'action' => 'put',
            'options' => [],
        ];

        $request['key'] = $key;
        $request['value'] = $value;

        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }
        if ($txn_id !== null) {
            $request['txn_id'] = $txn_id;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Retrieves the value associated with a key from the database.
     * This function handles the `get` action which fetches the value associated with a specified key from the RocksDB database.
     * The function can optionally operate within a specified column family and return a default value if the key is not found.
     *
     * @param string $key The key to get
     * @param string $cf_name The column family name
     * @param string $default_value The default value
     * @param int $txn_id The transaction ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function get(string $key, string $cf_name = null, string $default_value = null, int $txn_id = null) {
        $request = [
            'action' => 'get',
            'options' => [],
        ];

        $request['key'] = $key;

        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }
        if ($default_value !== null) {
            $request['default_value'] = $default_value;
        }
        if ($txn_id !== null) {
            $request['txn_id'] = $txn_id;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Deletes a key-value pair from the database.
     * This function handles the `delete` action which removes a specified key-value pair from the RocksDB database.
     * The function can optionally operate within a specified column family and transaction if provided.
     *
     * @param string $key The key to delete
     * @param string $cf_name The column family name
     * @param int $txn_id The transaction ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function delete(string $key, string $cf_name = null, int $txn_id = null) {
        $request = [
            'action' => 'delete',
            'options' => [],
        ];

        $request['key'] = $key;

        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }
        if ($txn_id !== null) {
            $request['txn_id'] = $txn_id;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Merges a value with an existing key in the database.
     * This function handles the `merge` action which merges a specified value with an existing key in the RocksDB database.
     * The function can optionally operate within a specified column family and transaction if provided.
     *
     * @param string $key The key to merge
     * @param string $value The value to merge
     * @param string $cf_name The column family name
     * @param int $txn_id The transaction ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function merge(string $key, string $value, string $cf_name = null, int $txn_id = null) {
        $request = [
            'action' => 'merge',
            'options' => [],
        ];

        $request['key'] = $key;
        $request['value'] = $value;

        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }
        if ($txn_id !== null) {
            $request['txn_id'] = $txn_id;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Retrieves a property of the database.
     * This function handles the `get_property` action which fetches a specified property of the RocksDB database.
     * The function can optionally operate within a specified column family if provided.
     *
     * @param string $value The property to get
     * @param string $cf_name The column family name
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function getProperty(string $value, string $cf_name = null) {
        $request = [
            'action' => 'get_property',
            'options' => [],
        ];

        $request['value'] = $value;

        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Retrieves a range of keys from the database.
     * This function handles the `keys` action which retrieves a range of keys from the RocksDB database.
     * The function can specify a starting index, limit on the number of keys, and a query string to filter keys.
     *
     * @param int $start The start index
     * @param int $limit The limit of keys to retrieve
     * @param string $query The query string to filter keys
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function keys(int $start, int $limit, string $query = null) {
        $request = [
            'action' => 'keys',
            'options' => [],
        ];

        $request['options']['start'] = $start;
        $request['options']['limit'] = $limit;

        if ($query !== null) {
            $request['options']['query'] = $query;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Retrieves all keys from the database.
     * This function handles the `all` action which retrieves all keys from the RocksDB database.
     * The function can specify a query string to filter keys.
     *
     * @param string $query The query string to filter keys
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function all(string $query = null) {
        $request = [
            'action' => 'all',
            'options' => [],
        ];


        if ($query !== null) {
            $request['options']['query'] = $query;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Lists all column families in the database.
     * This function handles the `list_column_families` action which lists all column families in the RocksDB database.
     * The function requires the path to the database.
     *
     * @param string $value The path to the database
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function listColumnFamilies(string $value) {
        $request = [
            'action' => 'list_column_families',
            'options' => [],
        ];

        $request['value'] = $value;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Creates a new column family in the database.
     * This function handles the `create_column_family` action which creates a new column family in the RocksDB database.
     * The function requires the name of the column family to create.
     *
     * @param string $cf_name The column family name to create
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function createColumnFamily(string $cf_name) {
        $request = [
            'action' => 'create_column_family',
            'options' => [],
        ];

        $request['cf_name'] = $cf_name;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Drops an existing column family from the database.
     * This function handles the `drop_column_family` action which drops an existing column family from the RocksDB database.
     * The function requires the name of the column family to drop.
     *
     * @param string $cf_name The column family name to drop
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function dropColumnFamily(string $cf_name) {
        $request = [
            'action' => 'drop_column_family',
            'options' => [],
        ];

        $request['cf_name'] = $cf_name;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Compacts a range of keys in the database.
     * This function handles the `compact_range` action which compacts a specified range of keys in the RocksDB database.
     * The function can optionally specify the start key, end key, and column family.
     *
     * @param string $start The start key
     * @param string $end The end key
     * @param string $cf_name The column family name
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function compactRange(string $start = null, string $end = null, string $cf_name = null) {
        $request = [
            'action' => 'compact_range',
            'options' => [],
        ];


        if ($start !== null) {
            $request['options']['start'] = $start;
        }
        if ($end !== null) {
            $request['options']['end'] = $end;
        }
        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Adds a key-value pair to the current write batch.
     * This function handles the `write_batch_put` action which adds a specified key-value pair to the current write batch.
     * The function can optionally operate within a specified column family.
     *
     * @param string $key The key to put
     * @param string $value The value to put
     * @param string $cf_name The column family name
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function writeBatchPut(string $key, string $value, string $cf_name = null) {
        $request = [
            'action' => 'write_batch_put',
            'options' => [],
        ];

        $request['key'] = $key;
        $request['value'] = $value;

        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Merges a value with an existing key in the current write batch.
     * This function handles the `write_batch_merge` action which merges a specified value with an existing key in the current write batch.
     * The function can optionally operate within a specified column family.
     *
     * @param string $key The key to merge
     * @param string $value The value to merge
     * @param string $cf_name The column family name
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function writeBatchMerge(string $key, string $value, string $cf_name = null) {
        $request = [
            'action' => 'write_batch_merge',
            'options' => [],
        ];

        $request['key'] = $key;
        $request['value'] = $value;

        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Deletes a key from the current write batch.
     * This function handles the `write_batch_delete` action which deletes a specified key from the current write batch.
     * The function can optionally operate within a specified column family.
     *
     * @param string $key The key to delete
     * @param string $cf_name The column family name
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function writeBatchDelete(string $key, string $cf_name = null) {
        $request = [
            'action' => 'write_batch_delete',
            'options' => [],
        ];

        $request['key'] = $key;

        if ($cf_name !== null) {
            $request['cf_name'] = $cf_name;
        }

        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Writes the current write batch to the database.
     * This function handles the `write_batch_write` action which writes the current write batch to the RocksDB database.
     *
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function writeBatchWrite() {
        $request = [
            'action' => 'write_batch_write',
            'options' => [],
        ];



        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Clears the current write batch.
     * This function handles the `write_batch_clear` action which clears the current write batch.
     *
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function writeBatchClear() {
        $request = [
            'action' => 'write_batch_clear',
            'options' => [],
        ];



        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Destroys the current write batch.
     * This function handles the `write_batch_destroy` action which destroys the current write batch.
     *
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function writeBatchDestroy() {
        $request = [
            'action' => 'write_batch_destroy',
            'options' => [],
        ];



        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Creates a new iterator for the database.
     * This function handles the `create_iterator` action which creates a new iterator for iterating over the keys in the RocksDB database.
     *
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function createIterator() {
        $request = [
            'action' => 'create_iterator',
            'options' => [],
        ];



        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Destroys an existing iterator.
     * This function handles the `destroy_iterator` action which destroys an existing iterator in the RocksDB database.
     * The function requires the ID of the iterator to destroy.
     *
     * @param int $iterator_id The iterator ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function destroyIterator(int $iterator_id) {
        $request = [
            'action' => 'destroy_iterator',
            'options' => [],
        ];

        $request['options']['iterator_id'] = $iterator_id;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Seeks to a specific key in the iterator.
     * This function handles the `iterator_seek` action which seeks to a specified key in an existing iterator in the RocksDB database.
     * The function requires the ID of the iterator, the key to seek, and the direction of the seek (Forward or Reverse).
     *
     * @param int $iterator_id The iterator ID
     * @param string $key The key to seek
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function iteratorSeek(int $iterator_id, string $key) {
        $request = [
            'action' => 'iterator_seek',
            'options' => [],
        ];

        $request['options']['iterator_id'] = $iterator_id;
        $request['key'] = $key;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Advances the iterator to the next key.
     * This function handles the `iterator_next` action which advances an existing iterator to the next key in the RocksDB database.
     * The function requires the ID of the iterator.
     *
     * @param int $iterator_id The iterator ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function iteratorNext(int $iterator_id) {
        $request = [
            'action' => 'iterator_next',
            'options' => [],
        ];

        $request['options']['iterator_id'] = $iterator_id;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Moves the iterator to the previous key.
     * This function handles the `iterator_prev` action which moves an existing iterator to the previous key in the RocksDB database.
     * The function requires the ID of the iterator.
     *
     * @param int $iterator_id The iterator ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function iteratorPrev(int $iterator_id) {
        $request = [
            'action' => 'iterator_prev',
            'options' => [],
        ];

        $request['options']['iterator_id'] = $iterator_id;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Creates a backup of the database.
     * This function handles the `backup` action which creates a backup of the RocksDB database.
     *
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function backup() {
        $request = [
            'action' => 'backup',
            'options' => [],
        ];



        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Restores the database from the latest backup.
     * This function handles the `restore_latest` action which restores the RocksDB database from the latest backup.
     *
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function restoreLatest() {
        $request = [
            'action' => 'restore_latest',
            'options' => [],
        ];



        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Restores the database from a specified backup.
     * This function handles the `restore` action which restores the RocksDB database from a specified backup.
     * The function requires the ID of the backup to restore.
     *
     * @param int $backup_id The ID of the backup to restore
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function restore(int $backup_id) {
        $request = [
            'action' => 'restore',
            'options' => [],
        ];

        $request['options']['backup_id'] = $backup_id;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Retrieves information about all backups.
     * This function handles the `get_backup_info` action which retrieves information about all backups of the RocksDB database.
     *
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function getBackupInfo() {
        $request = [
            'action' => 'get_backup_info',
            'options' => [],
        ];



        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Begins a new transaction.
     * This function handles the `begin_transaction` action which begins a new transaction in the RocksDB database.
     *
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function beginTransaction() {
        $request = [
            'action' => 'begin_transaction',
            'options' => [],
        ];



        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Commits an existing transaction.
     * This function handles the `commit_transaction` action which commits an existing transaction in the RocksDB database.
     * The function requires the ID of the transaction to commit.
     *
     * @param int $txn_id The transaction ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function commitTransaction(int $txn_id) {
        $request = [
            'action' => 'commit_transaction',
            'options' => [],
        ];

        $request['txn_id'] = $txn_id;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }


    /**
     * Rolls back an existing transaction.
     * This function handles the `rollback_transaction` action which rolls back an existing transaction in the RocksDB database.
     * The function requires the ID of the transaction to roll back.
     *
     * @param int $txn_id The transaction ID
     * 
     * @return mixed The result of the operation.
     * @throws Exception If the operation fails.
     */
    public function rollbackTransaction(int $txn_id) {
        $request = [
            'action' => 'rollback_transaction',
            'options' => [],
        ];

        $request['txn_id'] = $txn_id;


        $response = $this->sendRequest($request);
        return $this->handleResponse($response);
    }

}
