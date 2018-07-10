# magegen
Magento 2 generators to speed up development

## About
`magegen` takes a MySQL dump as input, and produces the following:

1. `Setup\InstallSchema` to install all tables found.
2. Interface models for each table.
3. Domain models for each table.
4. Resource models for each table.

These files will be generated using a fairly vanilla directory structure under the `./generated` directory relative to where the script is run from.

## Usage
`php run.php path/to/mysql/file.sql`

The following arguments are optional:

`--module-name=My_Module`

Set the module name to `My_Module` so the input isn't required.

`--vendor=Vendor`

Set the vendor to `Vendor` so the input isn't required.

`--destination=destination/directory`

Force the destination directory to `./destination/directory`. Must be relative and **will** overwrite files.

`--no-verify`

Skip the confirmation before beginning.

If they aren't provided at the command line, the `module-name` and `vendor` will be prompted for. This is used to generate your namespace `Vendor\Module_Name` and also to try and detect the interface and model names to use for your tables.

For example you can run it without interaction as follows:

`php run.php database/mysql.sql --no-verify --vendor=Ewan --module-name=MageGen`

## Notes
This is a WIP. At the moment, I'm only really creating it to speed up development at work. As such, my priority is my time - so it's not going to be perfect, it's not necessarily conforming to best practices, and it might not be updated often. The classes doing much of the work here, `DbGen` and `InterfaceGen`, were originally created as standalone PHP scripts to hap-hazardly generate output for simple use, and have been ham-fisted into classes to allow a simpler, more automated approach. The structure and abstractions is pretty nightmarish at the moment, the work is pretty badly divided, and there's the distinct smell of boilerplating... I _might_ improve it in time.

`run.php` should only be executed from the command-line; this isn't for browsers.

It assumes tables will be named in the form `vendor_module_function`. Although it does a bit of filtering, it might trip up if your tables are named significantly differently. In this case, it would assume the resulting interface would be `FunctionInterface` and the model be `Function`. If your table was named `function` and neither `vendor` nor `module` contained `'function'` then it would assume `FunctionInterface` and `Function` as well. Additionally, since it converts names from `snake_case` to `camelCase` and clips anything non-alpha, it might occasionally double up on functions.

The function `getId()` is always added to the interface and model, but this may double up if your primary key is called something else. Although not a problem, it's something to be aware of. For instance if your primary key is `my_primary_key` then the functions `getId()` and `getMyPrimaryKey()` will both independently operate on the same data. There is no functional issue with this happening but it may be considered poor standard.

## Disclaimer
This **will** create directories and generate files on your system - it tries not to overwrite anything, and should only use a newly created directory for output.

However, as a general disclaimer, make sure you don't run it anywhere important stuff is located, as I take no responsibility for it messing up your files. I would highly encourage you to examine the code before blindly running it.
