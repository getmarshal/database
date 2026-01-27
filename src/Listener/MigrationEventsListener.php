<?php

declare(strict_types=1);

namespace Marshal\Database\Listener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Marshal\Database\ConfigProvider;
use Marshal\Database\Event\GenerateMigrationEvent;
use Marshal\Database\Event\RollbackMigrationEvent;
use Marshal\Database\Event\RunMigrationEvent;
use Marshal\Database\Event\SaveMigrationEvent;
use Marshal\Database\Event\SetupMigrationsEvent;
use Marshal\Database\Query;
use Marshal\Database\DatabaseManager;
use Marshal\Database\Schema\Type;
use Marshal\Database\Schema\TypeManager;
use Marshal\Utils\Logger\LoggerManager;

final class MigrationEventsListener
{
    public function __construct(private array $schemaConfig, private array $databaseConfig)
    {
    }

    public function onGenerateMigrationEvent(GenerateMigrationEvent $event): void
    {
        $database = $event->getDatabase();

        // gather the definitions
        $definitions = [];

        foreach ($this->schemaConfig['types'] ?? [] as $name => $typeConfig) {
            if (! isset($typeConfig['database']) || $typeConfig['database'] !== $database) {
                continue;
            }

            $definitions[$name] = TypeManager::get($name);
        }

        // generate the schema diff
        $dbalSchema = DatabaseManager::getConnection($database)->createSchemaManager();
        $fromSchema = $dbalSchema->introspectSchema();
        $toSchema = $this->buildContentSchema($definitions);
        $diff = $dbalSchema->createComparator()->compareSchemas($fromSchema, $toSchema);
        $event->setDiff($diff);
    }

    public function onRollbackMigrationEvent(RollbackMigrationEvent $event): void
    {
    }

    public function onRunMigrationEvent(RunMigrationEvent $event): void
    {
    }

    public function onSaveMigrationEvent(SaveMigrationEvent $event): void
    {
        try {
            Query::create(ConfigProvider::MIGRATION_TYPE)
                ->fromInput($event->toArray())
                ->execute();
            $event->setIsSuccess(TRUE);
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage());
        }
    }

    public function onSetupMigrationsEvent(SetupMigrationsEvent $event): void
    {
        try {
            $connection = DatabaseManager::getConnection();
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage());
            return;
        }

        if ($connection->createSchemaManager()->tableExists('migration')) {
            LoggerManager::get()->info("Migrations already setup");
            return;
        }

        // create the migrations table
        $type = TypeManager::get('database::migration');

        $schema = $this->buildContentSchema([$type]);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $createStmt) {
            $connection->executeStatement($createStmt);
        }
    }

    private function buildDatabaseType(Schema $schema, Type $type): Table
    {
        $table = $schema->createTable($type->getTable());
        foreach ($type->getProperties() as $property) {
            // prepare column options
            $columnOptions = [
                'notnull' => $property->getNotNull(),
                'default' => $property->getDefaultValue(),
                'autoincrement' => $property->isAutoIncrement(),
                'length' => $property->getLength(),
                'fixed' => $property->getFixed(),
                'precision' => $property->getPrecision(),
                'scale' => $property->getScale(),
                'platformOptions' => $property->getPlatformOptions(),
                'unsigned' => $property->getUnsigned(),
            ];

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
                localColumnNames: [$relation->getLocalProperty()->getName()],
                foreignColumnNames: [$relation->getRelationProperty()->getName()],
                options: [
                    'onUpdate' => $relation->getOnUpdate(),
                    'onDelete' => $relation->getOnDelete(),
                ],
                name: \strtolower("fk_{$type->getTable()}_{$relation->getIdentifier()}")
            );
        }

        return $table;
    }

    private function buildContentSchema(array $definition): Schema
    {
        $schema = new Schema();
        foreach ($definition as $type) {
            if (! $type instanceof Type) {
                continue;
            }
            
            $this->buildDatabaseType($schema, $type);
        }

        return $schema;
    }
}
