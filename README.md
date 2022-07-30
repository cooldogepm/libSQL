# libSQL

A minimalistic implementation of asynchronous [SQL](https://en.wikipedia.org/wiki/SQL) for PHP.

## Examples

### Retrieve all customer records

```php
$pool = new ConnectionPool($this,
    [
        "provider" => "sqlite",
        "sqlite" => [
            "file" => "test.db"
        ]
    ]
);

$query = new class extends SQLiteQuery {

    public function onRun(SQLite3 $connection): void
    {
        $this->setResult($connection->query($this->getQuery())?->fetchArray() ?: null);
    }

    public function getQuery(): string
    {
        return "SELECT * FROM " . $this->getTable();
    }
};

$pool->submit($query, "customers",
    context: ClosureContext::create(
        function (?array $customers): void {
            if (!$customers) {
                echo "No customers found";
                return;
            }
            foreach ($customers as $customer) {
                echo $customer["name"];
            }
        },
    )
);
```

### Create a new customer record

```php
$query = new class extends SQLiteQuery {
    public function __construct(
        protected string $name = "John",
        protected string $lastName = "Smith",
        protected int    $age = 40
    ) {}

    public function getName(): string { return $this->name; }

    public function getLastName(): string { return $this->lastName; }

    public function getAge(): int { return $this->age; }

    public function onRun(SQLite3 $connection): void
    {
        $statement = $connection->prepare($this->getQuery());
        $statement->bindValue(":name", $this->getName());
        $statement->bindValue(":lastName", $this->getLastName());
        $statement->bindValue(":age", $this->getAge());
        $statement->execute();
        $statement->close();
    }

    public function getQuery(): string
    {
        return "INSERT OR IGNORE INTO " . $this->getTable() . " (name, lastName, age) VALUES (:name, :lastName, :age)";
    }
};

$pool->submit($query, "customers",
    context: ClosureContext::create(
        function (): void {
            echo "Successfully created a new customer record";
        },
    )
);
```

### Running from source code

Clone the repository via git `git clone git@github.com:cooldogedev/libSQL.git`. This will create a `libSQL` folder in your directory.

```
your_plugin
| -> src
|    --> cooldogedev
|       --> libSQL
```

- Place the `cooldogedev\libSQL` folder in your `src` directory.

### Projects using libSQL
- [BedrockEconomy](https://github.com/cooldogedev/BedrockEconomy)
- [TNTTag](https://github.com/cooldogedev/TNTTag)
