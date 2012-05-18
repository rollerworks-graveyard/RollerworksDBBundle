[RollerworksDBBundle]
=====================

### UserErrorExceptionListener

This listener listens to DBAL related Exceptions and looks for an so-called user-error.

** Currently only PostgreSQL is supported. **

An user-error is an exception/error thrown by an DB used-defined function,
and can be seen as a system exception, so its not intended for validating basic user-input.

Usage may include an access-violation or none-existent relation.

## Installation

### Step 1: Using Composer (recommended)

To install RollerworksDBBundle with Composer just add the following to your
`composer.json` file:

```js
// composer.json
{
    // ...
    require: {
        // ...
        "rollerworks/db-bundle": "master-dev"
    }
}
```

**NOTE**: Please replace `master-dev` in the snippet above with the latest stable
branch, for example ``2.0.*``.

Then, you can install the new dependencies by running Composer's ``update``
command from the directory where your ``composer.json`` file is located:

```bash
$ php composer.phar update
```

Now, Composer will automatically download all required files, and install them
for you. All that is left to do is to update your ``AppKernel.php`` file, and
register the new bundle:

```php
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new Rollerworks\Bundle\DBBundle\RollerworksDBBundle(),
    // ...
);
```

### Step 1 (alternative): Using ``deps`` file (Symfony 2.0.x)

First, checkout a copy of the code. Just add the following to the ``deps``
file of your Symfony Standard Distribution:

```ini
[RollerworksDBBundle]
    git=http://github.com/rollerscapes/RollerworksDBBundle.git
    target=/bundles/Rollerworks/Bundle/DBBundle
```

**NOTE**: You can add `version` tag in the snippet above with the latest stable
branch, for example ``version=origin/2.0``.

Then register the bundle with your kernel:

```php
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new Rollerworks\Bundle\DBBundle\RollerworksDBBundle(),
    // ...
);
```

Make sure that you also register the namespace with the autoloader:

```php
<?php

// app/autoload.php
$loader->registerNamespaces(array(
    // ...
    'Rollerworks'              => __DIR__.'/../vendor/bundles',
    // ...
));
```

Now use the ``vendors`` script to clone the newly added repositories
into your project:

```bash
$ php bin/vendors install
```

### Step 1 (alternative): Using submodules (Symfony 2.0.x)

If you're managing your vendor libraries with submodules, first create the
`vendor/bundles/Rollerworks/Bundle` directory:

``` bash
$ mkdir -pv vendor/bundles/Rollerworks/Bundle
```

Next, add the necessary submodule:

``` bash
$ git submodule add git://github.com/rollerscapes/RollerworksDBBundle.git vendor/bundles/Rollerworks/Bundle/DBBundle
```

### Step2: Configure the autoloader

Add the following entry to your autoloader:

``` php
<?php
// app/autoload.php

$loader->registerNamespaces(array(
    // ...
    'Rollerworks'              => __DIR__.'/../vendor/bundles',
    // ...
));
```

### Step3: Enable the bundle

Finally, enable the bundle in the kernel:

``` php
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new Rollerworks\Bundle\DBBundle\RollerworksDBBundle(),
    // ...
);
```

Congratulations! You're ready!

### Step4: Configure the bundle

By default the exception listerner only listens to \PDOException and \Doctrine\DBAL\Driver\OCI8\OCI8Exception.
And only tries to parse the error message when its starts with 'app-exception: '.

You may change this by adding this to your configuration.

``` yaml
# app/config/config.yml

rollerworks_db:
    user_exception_listener:
        check_prefix: 'my-app-exception: '
        check_class_in: [ '\PDOException' ]
```
