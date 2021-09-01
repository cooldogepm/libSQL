# libSQL

A minimalistic implementation of asynchronous [SQL](https://en.wikipedia.org/wiki/SQL) for PHP.

## Installation via DEVirion

Install the [DEVirion](https://poggit.pmmp.io/ci/poggit/devirion/DEVirion) plugin and start your server. This will create a `virions` folder in your server's root directory.

```
server_root
| -> plugins
|    --> DEVirion.phar
| -> virions
```

- Download pre-compiled `.phar` files can be downloaded from [poggit](https://poggit.pmmp.io/ci/cooldogedev/libSQL/libSQL).
- Place the pre-compiled `.phar` in the `virions` directory

## Running from source code

Clone the repository via git `git clone git@github.com:cooldogedev/libSQL.git`. This will create a `libSQL` folder in your directory.

```
your_plugin
| -> src
|    --> cooldogedev
|       --> libSQL
```

- Place the `cooldogedev\libSQL` folder in your `src` directory.

## Examples

### Retrieve all customer records

```php
$connector = new DatabaseConnector($this,
    [
        "provider" => "sqlite",
        "sqlite" => [
            "data-file" => "test.db"
        ]
    ]
);

$query = new class extends SQLiteQuery {

    public function handleIncomingConnection(SQLite3 $connection): ?array
    {
        return $connection->query($this->getQuery())?->fetchArray() ?: null;
    }

    public function getQuery(): string
    {
        return "SELECT * FROM " . $this->getTable();
    }
};

$connector->submitQuery($query, "customers",
    function (?array $customers): void {
        if (!$customers) {
            echo "No customers found";
            return;
        }
        foreach ($customers as $customer) {
            echo $customer["name"];
        }
    },
    function (PromiseError $error): void {
        echo "An error occurred with the message " . $error->getMessage();
    }
);
```

### Create a new customer record

```php
$connector = new DatabaseConnector($this,
    [
        "provider" => "sqlite",
        "sqlite" => [
            "data-file" => "test.db"
        ]
    ]
);

$query = new class extends SQLiteQuery {
    public function __construct(
        protected string $name = "John",
        protected string $lastName = "Smith",
        protected int    $age = 40
    ) {}

    public function getName(): string { return $this->name; }

    public function getLastName(): string { return $this->lastName; }

    public function getAge(): int { return $this->age; }

    public function handleIncomingConnection(SQLite3 $connection): bool
    {
        $statement = $connection->prepare($this->getQuery());
        $statement->bindValue(":name", $this->getName());
        $statement->bindValue(":lastName", $this->getLastName());
        $statement->bindValue(":age", $this->getAge());
        $statement->execute();
        $statement->close();
        return true;
    }

    public function getQuery(): string
    {
        return "INSERT OR IGNORE INTO " . $this->getTable() . " (name, lastName, age) VALUES (:name, :lastName, :age)";
    }
};

$connector->submitQuery($query, "customers",
    function (): void {
        echo "Successfully created a new record!";
    },
    function (PromiseError $error): void {
        echo "An error occurred with the message " . $error->getMessage();
    }
);
```

### Projects using libSQL
- [BedrockEconomy](https://github.com/cooldogedev/BedrockEconomy)
