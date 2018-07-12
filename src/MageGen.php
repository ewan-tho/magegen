<?php

namespace MageGen;

class MageGen
{
    const TAB                       = '    ';
    const INSTALL_SCHEMA_PATH       = 'Setup';
    const INTERFACE_PATH            = 'Api/Data';
    const REPOSITORY_INTERFACE_PATH = 'Api';
    const MODEL_PATH                = 'Model';
    const REPOSITORY_PATH           = 'Model';
    const RESOURCE_MODEL_PATH       = 'Model/ResourceModel';

    protected $generatedCount = [
        'models'          => 0,
        'interfaces'      => 0,
        'repositories'    => 0,
        'resource_models' => 0,
    ];

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
        $destination = null,
        $forceDestination = false
    ) {
        if (!empty($destination)) {
            $this->destination = $destination;
        }
        $this->forceDestination = $forceDestination;

        $this->moduleName = $moduleName;
        $this->vendor     = $vendor;

        if ($filePath) {
            $this->setFilePath($filePath);
            $this->setup();
        }

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
        $this->destination = str_replace('..','', $this->destination);
        while (strpos($this->destination, '//') !== false) {
            $this->destination = str_replace('//', '/', $this->destination);
        }
        if ($this->forceDestination) {
            return;
        }
        $counter = 0;
        $destinationPrefix = $this->destination;
        $this->destination = '';
        while (empty($this->destination) || file_exists($this->destination)) {
            $directoryName     = date('Y-m-d_H-i-s') . ($counter ? '-' . $counter : '');
            $this->destination = $destinationPrefix . DIRECTORY_SEPARATOR . $directoryName;
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
        $paths = [
            explode(DIRECTORY_SEPARATOR, $this->destination),
            explode(DIRECTORY_SEPARATOR, $this->destination . DIRECTORY_SEPARATOR . self::INSTALL_SCHEMA_PATH),
            explode(DIRECTORY_SEPARATOR, $this->destination . DIRECTORY_SEPARATOR . self::MODEL_PATH),
            explode(DIRECTORY_SEPARATOR, $this->destination . DIRECTORY_SEPARATOR . self::INTERFACE_PATH),
            explode(DIRECTORY_SEPARATOR, $this->destination . DIRECTORY_SEPARATOR . self::RESOURCE_MODEL_PATH),
        ];
        foreach ($paths as $path) {
            $fullPath = '';
            $path     = array_filter($path);
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
     *
     * @throws \Exception
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

            $this->buildResourceModelAndFile($modelName, empty($primaryKey) ? 'id' : $primaryKey['field'], $table['table']);
            $this->buildRepositoryAndFiles($modelName, $table['table']);
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
            $interfaceContent = str_replace('{{CONSTANTS}}', self::TAB . trim(implode("\n" . str_repeat(self::TAB, 1), $constants)), $interfaceContent);
            $interfaceContent = str_replace('{{FUNCTIONS}}', implode("\n", $interfaceFunctions), $interfaceContent);
            $interfaceFile    = $this->destination . DIRECTORY_SEPARATOR . self::INTERFACE_PATH . DIRECTORY_SEPARATOR . $interfaceName . '.php';
            file_put_contents($interfaceFile, $interfaceContent);
            $this->generatedCount['interfaces']++;
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
            $modelContent  = str_replace('{{FUNCTIONS}}', implode("\n", $modelFunctions), $modelContent);
            $modelFile     = $this->destination . DIRECTORY_SEPARATOR . self::MODEL_PATH . DIRECTORY_SEPARATOR . $modelName . '.php';
            file_put_contents($modelFile, $modelContent);
            $this->generatedCount['models']++;
        }
    }

    /**
     * @param      $interfaceName
     * @param      $fieldData
     * @param bool $replaceId
     *
     * @throws \Exception
     */
    private function buildInterfaceFunction(
        $interfaceName,
        $fieldData,
        $replaceId = false
    ) {
        $interfaceName = $this->convertToCamelCase($interfaceName);

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

        $keyName                      = strtoupper($itemName);
        $interfaceFunctionTemplateGet = $this->loadTemplate('InterfaceFunctionGet');
        $interfaceFunctionTemplateSet = $this->loadTemplate('InterfaceFunctionSet');
        $argument                     = '$' . $this->convertToCamelCase($functionName, true);
        $arguments                    = $argument;
        $params                       = $fieldData['type'] . ' ' . $argument;

        $templateVars = [
            'RETURNS'       => $fieldData['type'],
            'FUNCTION_NAME' => $functionName,
            'ARGUMENTS'     => $arguments,
            'PARAMS'        => $params
        ];

        $interfaceFunctionContent = $this->applyTemplate($templateVars, $interfaceFunctionTemplateGet);
        if (!$replaceId && empty($fieldData['primary'])) {
            $interfaceFunctionContent .= "\n" . $this->applyTemplate($templateVars, $interfaceFunctionTemplateSet);
        }


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

    /**
     * @param      $modelName
     * @param      $interfaceName
     * @param      $fieldData
     * @param bool $replaceId
     *
     * @throws \Exception
     */
    private function buildModelFunction(
        $modelName,
        $interfaceName,
        $fieldData,
        $replaceId = false
    ) {
        $modelName = $this->convertToCamelCase($modelName);

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

        $itemName                 = $this->convertToSnakeCase($this->filterName($fieldData['field']));
        $keyName                  = strtoupper($itemName);
        $modelFunctionTemplateGet = $this->loadTemplate('ModelFunctionGet');
        $modelFunctionTemplateSet = $this->loadTemplate('ModelFunctionSet');
        $argument                 = '$' . $this->convertToCamelCase($functionName, true);
        $arguments                = $argument;
        $params                   = $fieldData['type'] . ' ' . $argument;

        $templateVars = [
            'RETURNS'       => $fieldData['type'],
            'FUNCTION_NAME' => $functionName,
            'ARGUMENTS'     => $arguments,
            'PARAMS'        => $params,
            'KEY_NAME'      => $keyName,
            'ARGUMENT'      => $argument
        ];

        $modelFunctionContent = $this->applyTemplate($templateVars, $modelFunctionTemplateGet);
        if (!$replaceId && empty($fieldData['primary'])) {
            $modelFunctionContent .= "\n" . $this->applyTemplate($templateVars, $modelFunctionTemplateSet);
        }

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

    /**
     * @param $modelName
     * @param $primaryKeyName
     * @param $tableName
     *
     * @throws \Exception
     */
    private function buildResourceModelAndFile(
        $modelName,
        $primaryKeyName,
        $tableName
    ) {
        $modelName = $this->convertToCamelCase($modelName);

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

        $resourceModelTemplate           = $this->applyTemplate(array_merge($templateVars, ['NAMESPACE' => $resourceModelNamespace]), $resourceModelTemplate);
        $resourceModelCollectionTemplate = $this->applyTemplate(array_merge($templateVars, ['NAMESPACE' => $resourceModelNamespace . '\\' . $modelName]), $resourceModelCollectionTemplate);

        $this->generatedCount['resource_models']++;

        file_put_contents($resourceModelPath . '.php', $resourceModelTemplate);
        file_put_contents($resourceModelPath . DIRECTORY_SEPARATOR . 'Collection.php', $resourceModelCollectionTemplate);
    }

    /**
     * @param string   $modelName
     * @param string[] $table
     *
     * @throws \Exception
     */
    private function buildRepositoryAndFiles(
        $modelName,
        $table
    ) {
        $modelName                    = $this->convertToCamelCase($modelName);
        $repositoryName               = $modelName . 'Repository';
        $repositoryInterfaceName      = $modelName . 'RepositoryInterface';
        $interfaceName                = $modelName . 'Interface';
        $interfaceNamespace           = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::INTERFACE_PATH);
        $repositoryNamespace          = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::REPOSITORY_PATH);
        $repositoryInterfaceNamespace = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::REPOSITORY_INTERFACE_PATH);
        $modelPath                    = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::MODEL_PATH);
        $repositoryFile               = $this->destination . DIRECTORY_SEPARATOR . self::REPOSITORY_PATH . DIRECTORY_SEPARATOR . $repositoryName . '.php';
        $repositoryInterfaceFile      = $this->destination . DIRECTORY_SEPARATOR . self::REPOSITORY_INTERFACE_PATH . DIRECTORY_SEPARATOR . $repositoryInterfaceName . '.php';
        $resourceModelNamespace       = $this->vendor . '\\' . $this->moduleName . '\\' . str_replace('/', '\\', self::RESOURCE_MODEL_PATH);

        $templateVars = [
            'INTERFACE_PATH'                 => $interfaceNamespace . '\\' . $interfaceName,
            'INTERFACE_NAME'                 => $interfaceName,
            'REPOSITORY_NAME'                => $repositoryName,
            'REPOSITORY_NAMESPACE'           => $repositoryNamespace,
            'REPOSITORY_INTERFACE_NAME'      => $repositoryInterfaceName,
            'REPOSITORY_INTERFACE_PATH'      => $repositoryInterfaceNamespace . '\\' . $repositoryInterfaceName,
            'REPOSITORY_INTERFACE_NAMESPACE' => $repositoryInterfaceNamespace,
            'ARGUMENT'                       => '$' . $this->convertToCamelCase($modelName, true),
            'MODEL_NAME'                     => $modelName,
            'MODEL_NAME_LC'                  => $this->convertToCamelCase($modelName, true),
            'MODEL_PATH'                     => $modelPath . '\\' . $modelName,
            'RESOURCE_MODEL_PATH'            => $resourceModelNamespace . '\\' . $modelName
        ];

        $repositoryTemplate          = $this->applyTemplate($templateVars, $this->loadTemplate('Repository'));
        $repositoryInterfaceTemplate = $this->applyTemplate($templateVars, $this->loadTemplate('RepositoryInterface'));

        file_put_contents($repositoryFile, $repositoryTemplate);
        file_put_contents($repositoryInterfaceFile, $repositoryInterfaceTemplate);

        $this->generatedCount['repositories']++;
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
     * @param string[] $templateVars
     * @param string   $templateData
     *
     * @return string
     */
    private function applyTemplate(
        $templateVars,
        $templateData
    ) {
        foreach ($templateVars as $var => $value) {
            $templateData = str_replace('{{' . $var . '}}', $value, $templateData);
        }

        return $templateData;
    }

    /**
     * @param string $template
     *
     * @return bool|null|string
     * @throws \Exception
     */
    private function loadTemplate($template)
    {
        $template = str_replace([DIRECTORY_SEPARATOR, '/', '\\', '.'], '', $template);
        $fileName = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template . '.template';
        if (file_exists($fileName)) {
            $templateData = file_get_contents($fileName);

            return trim($templateData, "\n") . "\n";
        } else {
            throw new \Exception('Template ' . $template . ' not found (' . $fileName . ')');
        }
    }

    private function writeFile($path, $content)
    {
        echo "Writing to $path\n";
        file_put_contents($path, $content);
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

    /**
     * @param $countType
     *
     * @return int
     */
    public function getGeneratedCount($countType)
    {
        return empty($this->generatedCount[$countType]) ? 0 : $this->generatedCount[$countType];
    }
}
