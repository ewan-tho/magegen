<?php

namespace MageGen;

class MageGen
{
    const TAB                 = '    ';
    const INSTALL_SCHEMA_PATH = 'Setup';
    const INTERFACE_PATH      = 'Api/Data';
    const MODEL_PATH          = 'Model';
    const RESOURCE_MODEL_PATH = 'Model/ResourceModel';

    protected $filePath;

    protected $fileData;

    protected $vendor;

    protected $moduleName;

    protected $destination;

    protected $forceDestination = false;

    protected $models = [];

    protected $modelFunctions = [];

    protected $modelInterfaces = [];

    protected $interfaces = [];

    protected $interfaceFunctions = [];

    protected $interfaceConstants = [];

    /** @var DbGen */
    protected $dbGen;

    /**
     * MageGen constructor.
     *
     * @param string|null $filePath
     * @param string|null $vendor
     * @param string|null $moduleName
     * @param string|null $destination
     *
     * @throws \Exception
     */
    public function __construct(
        $filePath = null,
        $vendor = null,
        $moduleName = null,
        $destination = null
    ) {
        if ($filePath) {
            $this->setFilePath($filePath);
            $this->setup();
        }

        if (!empty($destination)) {
            $this->destination      = $destination;
            $this->forceDestination = true;
        }

        $this->moduleName = $moduleName;
        $this->vendor     = $vendor;

        $this->dbGen = new DbGen();
    }

    /**
     * @throws \Exception
     */
    public function beginRun()
    {
        $this->createDestination();

        $databaseResult = $this->dbGen->processMysqlData($this->fileData);
        $this->buildInstallSchemaFile($databaseResult[0], $databaseResult[1]);
        $this->buildInterfacesAndModels($databaseResult[2]);
        $this->buildInterfaceAndModelFiles();
    }

    /**
     * @throws \Exception
     */
    private function setup()
    {
        $this->setupFile();
        $this->setupDestination();
    }

    /**
     * Check file exists and load into memory.
     *
     * @throws \Exception
     */
    private function setupFile()
    {
        if (empty($this->filePath)) {
            throw new \Exception('No file specified.');
        }
        if (!file_exists($this->filePath)) {
            throw new \Exception('File not found.');
        }
        $this->fileData = file_get_contents($this->filePath);
        if (!$this->fileData) {
            throw new \Exception('Empty file.');
        }
    }

    /**
     * Setup the destination where the files will be generated.
     *
     * @throws \Exception
     */
    private function setupDestination()
    {
        if (!empty($this->destination)) {
            if (!file_exists($this->destination)) {
                throw new \Exception('Destination specified does not exist or is inaccessible.');
            }

            return;
        }
        $counter = 0;
        while (empty($this->destination) || file_exists($this->destination)) {
            $directoryName     = date('Y-m-d_His') . ($counter ? $counter : '');
            $this->destination = '.' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . $directoryName;
            $counter++;
        }
    }

    /**
     * Iterates through destination creating each folder to ensure it exists.
     *
     * @throws \Exception
     */
    private function createDestination()
    {
        if (empty($this->destination)) {
            throw new \Exception('No destination set.');
        }
        if (file_exists($this->destination)) {
            if (!$this->forceDestination) {
                throw new \Exception('Destination ' . $this->destination . ' already exists.');
            }
        }
        $paths = [
            explode(DIRECTORY_SEPARATOR, $this->destination),
            explode(DIRECTORY_SEPARATOR, $this->destination . DIRECTORY_SEPARATOR . self::INSTALL_SCHEMA_PATH),
            explode(DIRECTORY_SEPARATOR, $this->destination . DIRECTORY_SEPARATOR . self::MODEL_PATH),
            explode(DIRECTORY_SEPARATOR, $this->destination . DIRECTORY_SEPARATOR . self::INTERFACE_PATH),
            explode(DIRECTORY_SEPARATOR, $this->destination . DIRECTORY_SEPARATOR . self::RESOURCE_MODEL_PATH),
        ];
        foreach ($paths as $path) {
            $fullPath = '.';
            foreach ($path as $item) {
                $fullPath .= DIRECTORY_SEPARATOR . $item;
                if (!file_exists($fullPath)) {
                    mkdir($fullPath) || die("Could not create path $fullPath\n");
                }
            }
        }
    }

    /**
     * @param string[] $functions
     * @param string[] $functionNames
     *
     * @return bool
     */
    private function buildInstallSchemaFile(
        $functions,
        $functionNames
    ) {
        $installSchemaFile = $this->destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::INSTALL_SCHEMA_PATH) . DIRECTORY_SEPARATOR . 'InstallSchema.php';

        $fileTemplate = $this->loadTemplate('InstallSchema');

        $fileTemplate = str_replace('{{NAMESPACE}}', $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::INSTALL_SCHEMA_PATH), $fileTemplate);

        $functionTemplate = $this->loadTemplate('InstallSchemaFunction');

        foreach ($functions as $key => $function) {
            $function        = str_replace('{{FUNCTION}}', $function, $functionTemplate);
            $function        = str_replace('{{FUNCTION_NAME}}', $functionNames[$key], $function);
            $functions[$key] = $function;
        }

        $functionCalls = [];
        foreach ($functionNames as $key => $functionName) {
            $functionCalls[] = ($key == 0 ? '' : str_repeat(self::TAB, 2)) . '$this->' . $functionName . '($setup);';
        }

        $fileTemplate = str_replace('{{FUNCTION_CALLS}}', implode("\n", $functionCalls), $fileTemplate);
        $fileTemplate = str_replace('{{FUNCTIONS}}', trim(implode("\n", $functions)), $fileTemplate);

        file_put_contents($installSchemaFile, $fileTemplate);

        return true;
    }

    /**
     * @param string[[]] $tables
     */
    private function buildInterfacesAndModels($tables)
    {

        $interfaces   = [];
        $keys         = [];
        $functions    = [];
        $functionList = [];

        foreach ($tables as $table) {
            $tableName = $table['table'];

            $baseName       = $this->filterName($tableName);
            $interfaceName  = preg_replace('#[^a-zA-Z]#', '', $baseName) . 'Interface';
            $modelName      = $baseName;
            $modelFunctions = [];

            $primaryKey = [];

            foreach ($table['data'] as $item) {

                $this->buildInterfaceFunction($interfaceName, $item);
                $this->buildModelFunction($modelName, $interfaceName, $item);

                if (!empty($item['primary'])) {
                    $primaryKey = $item;
                }
            }

            if (!in_array('Id', $this->modelFunctions[$modelName])) {
                if (!empty($primaryKey)) {
                    $this->buildInterfaceFunction($interfaceName, $primaryKey, true);
                    $this->buildModelFunction($modelName, $interfaceName, $primaryKey, true);
                }
            }

            $this->buildResourceModel($modelName, empty($primaryKey) ? 'id' : $primaryKey['field'], $table['table']);
        }

        return true;
    }

    private function buildInterfaceAndModelFiles()
    {

        $interfaceNamespace = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::INTERFACE_PATH);
        $modelNamespace     = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::MODEL_PATH);

        $interfaceTemplate = $this->loadTemplate('Interface');
        $interfaceTemplate = str_replace('{{NAMESPACE}}', $interfaceNamespace, $interfaceTemplate);
        $interfaceTemplate = str_replace('{{VENDOR}}', $this->vendor, $interfaceTemplate);
        $interfaceTemplate = str_replace('{{MODULE_NAME}}', $this->moduleName, $interfaceTemplate);

        foreach ($this->interfaceFunctions as $interfaceName => $interfaceFunctions) {
            $constants = [];
            foreach ($this->interfaceConstants[$interfaceName] as $value) {
                foreach ($value as $key => $item) {
                    $constants[] = 'const ' . $key . ' = \'' . addslashes($item) . '\';';
                }
            }
            $interfaceContent = str_replace('{{INTERFACE_NAME}}', $interfaceName, $interfaceTemplate);
            $interfaceContent = str_replace('{{CONSTANTS}}', trim(implode("\n" . str_repeat(self::TAB, 1), $constants)), $interfaceContent);
            $interfaceContent = str_replace('{{FUNCTIONS}}', trim(implode("\n" . str_repeat(self::TAB, 1), $interfaceFunctions)), $interfaceContent);
            $interfaceFile    = $this->destination . DIRECTORY_SEPARATOR . self::INTERFACE_PATH . DIRECTORY_SEPARATOR . $interfaceName . '.php';
            file_put_contents($interfaceFile, $interfaceContent);
        }

        $modelTemplate = $this->loadTemplate('Model');
        $modelTemplate = str_replace('{{NAMESPACE}}', $modelNamespace, $modelTemplate);
        $modelTemplate = str_replace('{{VENDOR}}', $this->vendor, $modelTemplate);
        $modelTemplate = str_replace('{{MODULE_NAME}}', $this->moduleName, $modelTemplate);
        $resourceModel = '\\' . $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::RESOURCE_MODEL_PATH) . '\\';

        foreach ($this->modelFunctions as $modelName => $modelFunctions) {
            $interfaceName = $this->modelInterfaces[$modelName];
            $modelContent  = str_replace('{{MODEL}}', $modelName, $modelTemplate);
            $modelContent  = str_replace('{{RESOURCE_MODEL}}', $resourceModel . $modelName, $modelContent);
            $modelContent  = str_replace('{{INTERFACE_NAME}}', $interfaceName, $modelContent);
            $modelContent  = str_replace('{{INTERFACE_PATH}}', $interfaceNamespace . '\\' . $interfaceName, $modelContent);
            $modelContent  = str_replace('{{FUNCTIONS}}', trim(implode("\n" . str_repeat(self::TAB, 1), $modelFunctions)), $modelContent);
            $modelFile     = $this->destination . DIRECTORY_SEPARATOR . self::MODEL_PATH . DIRECTORY_SEPARATOR . $modelName . '.php';
            file_put_contents($modelFile, $modelContent);
        }
    }

    private function buildInterfaceFunction(
        $interfaceName,
        $fieldData,
        $replaceId = false
    ) {
        if (!isset($this->interfaceFunctions[$interfaceName])) {
            $this->interfaceFunctions[$interfaceName] = [];
        }

        if (!isset($this->interfaceConstants[$interfaceName])) {
            $this->interfaceConstants[$interfaceName] = [];
        }

        if (!$replaceId) {
            $functionName = $this->convertToCamelCase($fieldData['field']);
        } else {
            $functionName = 'Id';
        }
        $itemName = $this->convertToSnakeCase($this->filterName($fieldData['field']));

        $keyName                   = strtoupper($itemName);
        $interfaceFunctionTemplate = $this->loadTemplate('InterfaceFunction');
        $argument                  = '$' . $this->convertToCamelCase($functionName, true);
        $arguments                 = $argument; // $fieldData['type'] . ' ' . $argument;
        $interfaceFunctionContent  = str_replace('{{RETURNS}}', $fieldData['type'], $interfaceFunctionTemplate);
        $interfaceFunctionContent  = str_replace('{{FUNCTION_NAME}}', $functionName, $interfaceFunctionContent);
        $interfaceFunctionContent  = str_replace(['{{ARGUMENTS}}', '{{PARAMS}}'], $arguments, $interfaceFunctionContent);

        if (!$replaceId) {
            $this->interfaceConstants[$interfaceName][]              = [$keyName => $itemName];
            $this->interfaceFunctions[$interfaceName][$functionName] = $interfaceFunctionContent;
        } else {
            if (isset($this->interfaceFunctions[$interfaceName][$functionName])) {
                unset($this->interfaceFunctions[$interfaceName][$functionName]);
            }
            $newFunction[$functionName]               = $interfaceFunctionContent;
            $this->interfaceFunctions[$interfaceName] = array_merge($newFunction, $this->interfaceFunctions[$interfaceName]);
        }
    }

    private function buildModelFunction(
        $modelName,
        $interfaceName,
        $fieldData,
        $replaceId = false
    ) {
        if (!isset($this->modelFunctions[$modelName])) {
            $this->modelFunctions[$modelName] = [];
        }

        if (!isset($this->modelFunctions[$modelName])) {
            $this->modelFunctions[$modelName] = [];
        }

        if (!$replaceId) {
            $functionName = $this->convertToCamelCase($fieldData['field']);
        } else {
            $functionName = 'Id';
        }

        $itemName              = $this->convertToSnakeCase($this->filterName($fieldData['field']));
        $keyName               = strtoupper($itemName);
        $modelFunctionTemplate = $this->loadTemplate('ModelFunction');
        $argument              = '$' . $this->convertToCamelCase($functionName, true);
        $arguments             = $argument; //$fieldData['type'] . ' ' . $argument;
        $modelFunctionContent  = str_replace('{{RETURNS}}', $fieldData['type'], $modelFunctionTemplate);
        $modelFunctionContent  = str_replace('{{FUNCTION_NAME}}', $functionName, $modelFunctionContent);
        $modelFunctionContent  = str_replace(['{{ARGUMENTS}}', '{{PARAMS}}'], $arguments, $modelFunctionContent);
        $modelFunctionContent  = str_replace('{{KEY_NAME}}', $keyName, $modelFunctionContent);
        $modelFunctionContent  = str_replace('{{ARGUMENT}}', $argument, $modelFunctionContent);

        if (!$replaceId) {
            $this->modelFunctions[$modelName][$functionName] = $modelFunctionContent;
            $this->modelInterfaces[$modelName]               = $interfaceName;
        } else {
            if (isset($this->modelFunctions[$modelName][$functionName])) {
                unset($this->modelFunctions[$modelName][$functionName]);
            }
            $newFunction[$functionName]       = $modelFunctionContent;
            $this->modelFunctions[$modelName] = array_merge($newFunction, $this->modelFunctions[$modelName]);
        }
    }

    private function buildResourceModel(
        $modelName,
        $primaryKeyName,
        $tableName
    ) {
        $resourceModelNamespace = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::RESOURCE_MODEL_PATH);

        $resourceModelPath = $this->destination . DIRECTORY_SEPARATOR . self::RESOURCE_MODEL_PATH . DIRECTORY_SEPARATOR . $modelName;
        if (!file_exists($resourceModelPath)) {
            mkdir($resourceModelPath);
        }

        $resourceModelTemplate           = $this->loadTemplate('ResourceModel');
        $resourceModelCollectionTemplate = $this->loadTemplate('ResourceModelCollection');

        $modelNamespace = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::MODEL_PATH);
        $modelPath      = $modelNamespace . '\\' . $modelName;
        $templateVars   = [
            'MODEL'               => $modelName,
            'MODEL_PATH'          => $modelPath,
            'RESOURCE_MODEL_PATH' => $resourceModelNamespace . '\\' . $modelName,
            'PRIMARY_KEY'         => $primaryKeyName,
            'TABLE'               => $tableName
        ];

        foreach ($templateVars as $var => $value) {
            $resourceModelTemplate           = str_replace('{{' . $var . '}}', $value, $resourceModelTemplate);
            $resourceModelCollectionTemplate = str_replace('{{' . $var . '}}', $value, $resourceModelCollectionTemplate);
        }

        $resourceModelTemplate           = str_replace('{{NAMESPACE}}', $resourceModelNamespace, $resourceModelTemplate);
        $resourceModelCollectionTemplate = str_replace('{{NAMESPACE}}', $resourceModelNamespace . '\\' . $modelName, $resourceModelCollectionTemplate);

        file_put_contents($resourceModelPath . '.php', $resourceModelTemplate);
        file_put_contents($resourceModelPath . DIRECTORY_SEPARATOR . 'Collection.php', $resourceModelCollectionTemplate);
    }

    private function convertToSnakeCase($variableName)
    {
        return substr(strtolower(preg_replace('#([^a-z])#', '_\1', $variableName)), 1);
    }

    /**
     * @param string $variableName
     */
    private function convertToCamelCase(
        $variableName,
        $startLowerCase = false
    ) {
        $variableName = implode('', array_filter(array_map(function ($item) {
            return ucwords($item);
        }, preg_split('#[^a-zA-Z]#', $variableName))));

        return $startLowerCase ? (strtolower(substr($variableName, 0, 1)) . substr($variableName, 1)) : $variableName;
    }

    /**
     * @param string $tableName
     *
     * @return string
     */
    private function filterName($tableName)
    {
        $tableName = preg_split('#[^a-z]#', $tableName);

        return implode('', array_filter(array_map(function ($item) {
            $item = strtolower($item);

            return ($item == strtolower($this->moduleName) || $item == strtolower($this->vendor)) ? null : ucwords($item);
        }, $tableName)));
    }

    /**
     * @param string $template
     *
     * @return bool|null|string
     */
    private function loadTemplate($template)
    {
        $template = str_replace([DIRECTORY_SEPARATOR, '/', '\\', '.'], '', $template);
        $fileName = '.' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template . '.template';
        if (file_exists($fileName)) {
            $templateData = file_get_contents($fileName);

            return trim($templateData) . "\n";
        }

        return null;
    }

    /**
     * @param string $filePath
     *
     * @throws \Exception
     */
    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return null|string
     */
    public function getDestination()
    {
        return $this->destination;
    }
}
