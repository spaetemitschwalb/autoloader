<?php

/**
 * Map general ObjectStorage.
 */
declare(strict_types=1);

namespace HDNET\Autoloader\Mapper;

use HDNET\Autoloader\MapperInterface;
use HDNET\Autoloader\Service\TyposcriptConfigurationService;
use HDNET\Autoloader\Utility\ModelUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Map general ObjectStorage.
 */
class ObjectStorageMapper implements MapperInterface
{
    /**
     * Check if the current mapper can handle the given type.
     *
     * @param string $type
     *
     * @return bool
     */
    public function canHandleType($type)
    {
        return false !== mb_stristr(trim($type, '\\'), 'typo3\\cms\\extbase\\persistence\\objectstorage');
    }

    /**
     * Get the TCA configuration for the current type.
     *
     * @param string $fieldName
     * @param bool   $overWriteLabel
     *
     * @return array
     */
    public function getTcaConfiguration($fieldName, $overWriteLabel = false)
    {
        $baseConfig = [
            'type' => 'user',
            'userFunc' => 'HDNET\\Autoloader\\UserFunctions\\Tca->objectStorageInfoField',
        ];

        return [
            'exclude' => 1,
            'label' => $overWriteLabel ? $overWriteLabel : $fieldName,
            'config' => $baseConfig,
        ];
    }

    /**
     * Get the database definition for the current mapper.
     *
     * @return string
     */
    public function getDatabaseDefinition()
    {
        return 'varchar(255) DEFAULT \'\' NOT NULL';
    }

    public function getJsonDefinition($type, $fieldName, $className, $extensionKey, $tableName)
    {
        $fieldNameUnderscored = GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);

        $braceStartPos = \strpos($type, '<');
        $braceEndPos = \strpos($type, '>');
        if (false === $braceStartPos || false === $braceEndPos) {
            throw new \RuntimeException('The ObjectStorage needs to have a template type!');
        }
        $objectStorageTemplateClassType = (new \ReflectionClass(\substr($type, $braceStartPos + 1, $braceEndPos - $braceStartPos - 1)))->getName();
        $objectStorageTemplateTableName = ModelUtility::getTableName($objectStorageTemplateClassType);

        $typoscriptConfigurationService = TyposcriptConfigurationService::getInstance();
        $typoscriptConfigurationService->pushSerialisationCache();
        $fields = $typoscriptConfigurationService->getTyposcriptConfiguration($objectStorageTemplateClassType, $extensionKey, $objectStorageTemplateTableName);
        $typoscriptConfigurationService->popSerialisationCache();
        $fieldString = \implode("\n", $fields);
        $objectStorageTemplateFieldRelationName = $typoscriptConfigurationService->getRelationDatabaseFieldNameFor($objectStorageTemplateClassType, $className);
        if (null === $objectStorageTemplateFieldRelationName) {
            return "
                {$fieldName} = TEXT
                {$fieldName}.dataProcessing {
                        10 = TYPO3\CMS\Frontend\DataProcessing\SplitProcessor
                        10 {
                            fieldName = {$fieldNameUnderscored}
                            delimiter = ,
                            removeEmptyEntries = 1
                            filterIntegers = 1
                            filterUnique = 1
                            as = {$fieldName}
                        }
                    }
            ";
        }

        return "
        {$fieldName} = CONTENT_JSON
        {$fieldName} {
            table = {$objectStorageTemplateTableName}
            select {
                pidInList = this
                where = {$objectStorageTemplateTableName}.{$objectStorageTemplateFieldRelationName}={field:uid}
                where.insertData = 1
            }

            renderObj = JSON
            renderObj.fields {
                {$fieldString}
            }
        }
        ";
    }
}
