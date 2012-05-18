[RollerworksDBBundle](http://projects.rollerscapes.net/RollerFramework/)
==================================================

This bundle provides the RollerworksDBBundle.

### UserErrorExceptionListener

This listener listens to pre-configured DB(AL) related Exceptions and looks for an so-called user-error.

***Currently only PostgreSQL is supported.***

An user-error is an exception/error thrown by an DB used-defined function,
and is intended as a 'last check', so don't use this to validate basic user-input.

Usage may include an access exception or none existent relation.

## Installation

Installation depends on how your project is setup:

### Step 1: Installation using the `bin/vendors.php` method

If you're using the `bin/vendors.php` method to manage your vendor libraries,
add the following entry to the `deps` in the root of your project file:

```
[RollerworksDBBundle]
    git=https://github.com/Rollerscapes/RollerworksDBBundle.git
    target=/vendor/bundles/Rollerworks/DBBundle
```

Next, update your vendors by running:

```bash
$ ./bin/vendors
```

Great! Now skip down to *Step 2*.

### Step 1 (alternative): Installation with sub-modules

If you're managing your vendor libraries with sub-modules, first create the
`vendor/bundles/Rollerworks/DBBundle` directory:

```bash
$ mkdir -pv vendor/bundles/Rollerworks/DBBundle
```

Next, add the necessary sub-module:

```bash
$ git submodule add https://github.com/Rollerscapes/RollerworksDBBundle.git vendor/bundles/Rollerworks/DBBundle
```

### Step2: Configure the autoloader

Add the following entry to your autoloader:

```php
<?php
// app/autoload.php

$loader->registerNamespaces(array(
    // ...
    'Rollerworks' => __DIR__.'/../vendor/bundles',
));
```

### Step3: Enable the bundle

Finally, enable the bundle in the kernel:

```php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Rollerworks\DBBundle\RollerworksDBBundle(),
    );
}
```

### Step4: Configure the bundle

Nothing needs to be configured ***explicitly***.
