<?php

declare(strict_types=1);

namespace Marshal\Database\Migration\Listener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Marshal\Database\DatabaseManager;
use Marshal\Database\Migration\Event\GenerateMigrationEvent;
use Marshal\Database\Migration\Event\MigrationTrait;
use Marshal\Database\Migration\Event\RollbackMigrationEvent;
use Marshal\Database\Migration\Event\RunMigrationEvent;
use Marshal\Database\Migration\Event\SetupMigrationsEvent;
use Marshal\Database\Migration\MigrationItem;
use Marshal\Database\Schema\Type;
use Marshal\Database\Schema\TypeManager;
use Marshal\Utils\Config;
use Marshal\Utils\Logger\LoggerManager;

final class MigrationEventsListener
{
    use MigrationTrait;

    public function onGenerateMigrationEvent(GenerateMigrationEvent $event): void
    {
        $database = $event->getDatabase();
        $connection = DatabaseManager::getConnection($database);
        $dbalSchema = $connection->createSchemaManager();
        if ($event->isTypeMigration()) {
            $schema = new Schema();
            $type = TypeManager::get($event->getTypeIdentifier());

            // migrations for a new type
            if (! $dbalSchema->tableExists($type->getTable())) {
                $this->buildDatabaseType($schema, $type);
                $event->setDiff($dbalSchema->createComparator()->compareSchemas(new Schema(), $schema));
                return;
            }

            // migrations for an existing type
            foreach ($dbalSchema->introspectSchema()->getTables() as $table) {
                if ($table->getName() === $type->getTable()) {
                    $this->buildDatabaseType($schema, $type);
                    $event->setDiff($dbalSchema->createComparator()->compareSchemas(new Schema([$table]), $schema));
                    return;
                }
            }

            // ostensibly, no type was found
            LoggerManager::get()->warning(\sprintf(
                "Migrations for type %s not found",
                $event->getTypeIdentifier()
            ));
        } else {
            // generate migrations for an entire database
            $definitions = [];
            $schemaConfig = Config::get('schema');
            foreach ($schemaConfig['types'] ?? [] as $name => $typeConfig) {
                if (! isset($typeConfig['database']) || $typeConfig['database'] !== $database) {
                    continue;
                }

                $definitions[$name] = TypeManager::get($name);
            }

            // generate the schema diff
            $fromSchema = $dbalSchema->introspectSchema();
            $toSchema = $this->buildContentSchema($definitions);
            $diff = $dbalSchema->createComparator()->compareSchemas($fromSchema, $toSchema);
            $event->setDiff($diff);
        }        
    }

    public function onRollbackMigrationEvent(RollbackMigrationEvent $event): void
    {
    }

    public function onRunMigrationEvent(RunMigrationEvent $event): void
    {
        $migration = $event->getMigration();

        // prepare target database
        $connection = DatabaseManager::getConnection($migration->getDatabase());

        // get migration statements
        $diff = $this->getMigrationDiff($migration);
        $statements = $connection->getDatabasePlatform()->getAlterSchemaSQL($diff);

        // @todo create separate execute event while returning statments for confirmation of process

        // execute statements
        $failedStatements = [];
        $reasons = [];
        foreach ($statements as $statement) {
            try {
                $connection->executeStatement($statement);
            } catch (\Throwable $e) {
                $failedStatements[] = $statement;
                $reasons[] = $e->getMessage();
                continue;
            }
        }

        if (! empty($failedStatements)) {
            LoggerManager::get()->error(
                \sprintf(
                    "Failed to execute one or more statements for migration %s",
                    $migration->getName()
                ),
                [
                    'statements' => $failedStatements,
                    'reasons' => $reasons
                ]);
            throw new \RuntimeException("Failed to execute one or more migration statements");
        }
    }

    public function onSetupMigrationsEvent(SetupMigrationsEvent $event): void
    {
        $type = TypeManager::get(MigrationItem::class);
        $connection = DatabaseManager::getConnection(MigrationItem::class);
        $table = $this->buildDatabaseType(new Schema(), $type);
        $connection->createSchemaManager()->createTable($table);
    }

    private function buildDatabaseType(Schema $schema, Type $type): Table
    {
        $table = $schema->createTable($type->getTable());
        foreach ($type->getProperties() as $property) {
            // prepare column options
            $columnOptions = [
                'notnull' => $property->getNotNull(),
                'autoincrement' => $property->isAutoIncrement(),
                'length' => $property->getLength(),
                'fixed' => $property->getFixed(),
                'precision' => $property->getPrecision(),
                'scale' => $property->getScale(),
                'platformOptions' => $property->getPlatformOptions(),
                'unsigned' => $property->getUnsigned(),
            ];

            if (null !== $property->getDefaultValue() && \is_scalar($property->getDefaultValue())) {
                $columnOptions['default'] = $property->getDefaultValue();
            }

            if ($property->hasDescription()) {
                $columnOptions['comment'] = $property->getDescription();
            }

            // add column to table
            // @todo handle exception thrown here
            $table->addColumn(
                name: $property->getName(),
                typeName: $property->getDatabaseTypeName(),
                options: $columnOptions
            );

            // autoincrementing properties are primary keys
            if ($property->isAutoIncrement()) {
                $table->setPrimaryKey([$property->getName()]);
            }

            // configure column index
            if ($property->hasIndex()) {
                $table->addIndex(
                    columnNames: [$property->getName()],
                    indexName: $property->getIndex()->getName() ?? \strtolower("idx_{$type->getTable()}_{$property->getName()}"),
                    flags: $property->getIndex()->getFlags(),
                    options: $property->getIndex()->getOptions()
                );
            }

            if ($property->hasUniqueConstraint()) {
                $constraint = $property->getUniqueConstraint();
                $table->addUniqueIndex(
                    columnNames: [$property->getName()],
                    indexName: $constraint->getName() ?? \strtolower("uniq_{$type->getTable()}_{$property->getName()}"),
                    options: $constraint->getOptions(),
                );
            }
        }

        foreach ($type->getRelations() as $relation)  {
            $table->addForeignKeyConstraint(
                foreignTableName: $relation->getRelationType()->getTable(),
                localColumnNames: [$type->getProperty($relation->getLocalProperty())->getName()],
                foreignColumnNames: [$relation->getRelationProperty()->getName()],
                options: [
                    'onUpdate' => $relation->getOnUpdate(),
                    'onDelete' => $relation->getOnDelete(),
                ],
                name: $relation->getIdentifier()
            );
        }

        return $table;
    }

    private function buildContentSchema(array $definition): Schema
    {
        $schema = new Schema();
        foreach ($definition as $type) {
            if (! $type instanceof Item) {
                continue;
            }
            
            $this->buildDatabaseType($schema, $type);
        }

        return $schema;
    }
}
