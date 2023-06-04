# libSQL

A minimalistic implementation of asynchronous [SQL](https://en.wikipedia.org/wiki/SQL) for PHP.

### Usage

#### Initialise the connection pool

```php
$pool = new ConnectionPool(PluginBase, [
    "provider" => "sqlite",
    "threads" => 2,
    "sqlite" => [
        "path" => "test.db"
    ]
]);
```

<br>

#### Examples

###### Retrieve all customer records

* Create the query class

```php
final class CustomerRetrievalQuery extends SQLiteQuery {
    public function onRun(SQLite3 $connection): void {
        $this->setResult($connection->query($this->getQuery())?->fetchArray() ?: []);
    }

    public function getQuery(): string { return "SELECT * FROM customers"; }
}
```

* Execute the query

```php
$query = new CustomerRetrievalQuery();
$query->execute(
    onSuccess: function (array $customers): void {
        foreach ($customers as $customer) {
            echo $customer["name"] . " " . $customer["lastName"] . ": " . $customer["age"];
            echo PHP_EOL;
        }
    },
    onFailure: function (SQLException $exception): void {
        echo "Failed to retrieve customers due to: " . $exception->getMessage();
    }
);
```

<br>

###### Create a new customer record

* Create the query class

```php
final class CustomerCreationQuery extends SQLiteQuery {
    public function __construct(
        protected string $name,
        protected string $lastName,
        protected int    $age
    ) {}

    public function onRun(SQLite3 $connection): bool {
        $statement = $connection->prepare($this->getQuery());

        $statement->bindValue(":name", $this->getName());
        $statement->bindValue(":lastName", $this->getLastName());
        $statement->bindValue(":age", $this->getAge());
        $statement->execute();

        $this->setResult($connection->changes() > 0);

        $statement->close();
    }

    public function getQuery(): string {
        return "INSERT OR IGNORE INTO customers (name, lastName, age) VALUES (:name, :lastName, :age)";
    }

    public function getName(): string { return $this->name; }
    public function getLastName(): string { return $this->lastName; }
    public function getAge(): int { return $this->age; }
}
```

* Execute the query

```php
$query = new CustomerCreationQuery("Saul", "Goodman", 41);
$pool->submit(
    query: $query,

    onSuccess: function (bool $created): void {
        echo $created ? "Customer created successfully!" : "Customer already exists!";
    },
    onFailure: function (SQLException $exception): void {
        echo "Failed to create the record due to: " . $exception->getMessage();
    }
);
```

### Projects using libSQL
- [BedrockEconomy](https://github.com/cooldogepm/BedrockEconomy)
- [BuildBattle](https://github.com/cooldogepm/BuildBattle)
- [TheBridges](https://github.com/cooldogepm/TheBridges)
- [TNTTag](https://github.com/cooldogepm/TNTTag)
