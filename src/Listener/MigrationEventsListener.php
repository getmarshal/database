<?php

declare(strict_types=1);

namespace Marshal\Database\Listener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Marshal\Database\Event\GenerateMigrationEvent;
use Marshal\Database\Event\RollbackMigrationEvent;
use Marshal\Database\Event\RunMigrationEvent;
use Marshal\Database\Event\SetupMigrationsEvent;
use Marshal\Database\DatabaseManager;
use Marshal\Database\Schema\Type;
use Marshal\Database\Schema\TypeManager;
use Marshal\Utils\Config;
use Marshal\Utils\Logger\LoggerManager;

final class MigrationEventsListener
{
    public function onGenerateMigrationEvent(GenerateMigrationEvent $event): void
    {
        $database = $event->getDatabase();
        $connection = DatabaseManager::getConnection($database);
        $dbalSchema = $connection->createSchemaManager();
        if ($event->isTypeMigration()) {
            $schema = new Schema();
            $type = TypeManager::get($event->getTypeIdentifier());
            if (! $dbalSchema->tableExists($type->getTable())) {
                $this->buildDatabaseType($schema, $type);
                $event->setDiff($dbalSchema->createComparator()->compareSchemas(new Schema(), $schema));
                return;
            }

            $definitions = [$type];
            foreach ($dbalSchema->introspectSchema()->getTables() as $table) {
                if ($table->getName() === $type->getTable()) {
                    $this->buildDatabaseType($schema, $type);
                    $event->setDiff($dbalSchema->createComparator()->compareSchemas(new Schema([$table]), $schema));
                    return;
                }
            }
        } else {
            $definitions = [];
            $schemaConfig = Config::get('schema');
            foreach ($schemaConfig['types'] ?? [] as $name => $typeConfig) {
                if (! isset($typeConfig['database']) || $typeConfig['database'] !== $database) {
                    continue;
                }

                // if ($event->isTypeMigration())
                $type = TypeManager::get($name);
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
    }

    public function onSetupMigrationsEvent(SetupMigrationsEvent $event): void
    {
        try {
            $connection = DatabaseManager::getConnection();
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage());
            return;
        }

        // create the migrations table
        $type = TypeManager::get('database::migration');
        if ($connection->createSchemaManager()->tableExists($type->getTable())) {
            LoggerManager::get()->info("Migrations already setup");
            return;
        }

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
