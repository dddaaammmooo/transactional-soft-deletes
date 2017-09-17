# Transactional Record Deletion For Eloquent Models
_(aka. Recycle Bin for Laravel)_

## Requirements

* PHP >= 7.1
* Laravel >= 5.5

## Installation

First, require the library into your project using composer by running the following command:

```
composer require dddaaammmooo/transactional-soft-deletes
```

Then, deploy the default migration and configuration files to setup the required database tables
by running the following command:

```
php artisan vendor:publish 
--provider="Cartalyst\Sentinel\Laravel\SentinelServiceProvider"
```

If you are happy with the default database schema you can now migrate to create the required tables.
Optionally you can modify the default migration files in `/laravel/database/migrations` to meet your 
needs. However if you change the column names you will need to update the default configuration in 
`/laravel/config/transactional-soft-deletes.php`.

```php
php artisan migrate
```

Next you need to add the `Dddaaammmooo\TransactionalSoftDeletes\Provider` class to
the `providers` section of your `/laravel/config/app.php` file.

```php
<?php
return [
    // ..

    'providers' => [
        // ...
        Dddaaammmooo\TransactionalSoftDeletes\Provider::class,
    ],
    
    // ..
    
];
```

Next create an alias for the transaction handling class by adding
the following to the `alias` section of the `/laravel/config/app.php` file.

```php
<?php

return [
    // ..

    'aliases' => [
        // ...
        'Transaction' => Dddaaammmooo\TransactionalSoftDeletes\Transaction::class,
    ],
    
    // ..
    
];
```

If everything went to plan it should now be ready to use.

## Usage

When creating models for use in your Laravel project extend from the `Dddaaammmooo\TransactionalSoftDeletes\Model` class.

```php
<?php

namespace App\Models;

class Person extends \Dddaaammmooo\TransactionalSoftDeletes\Model
{
    // Insert model code here
}
```

You can now use your models as normal and rather than the default Laravel
behaviour of storing `deleted_at` as a timestamp, you will now have a 
`delete_transaction_id` column populated with an ID number that is unique to each
delete transaction.

## Delete Transaction Scope

By default all deletions that are performed in a single page load cycle will be
assigned the same `delete_transaction_id` value.

There may be times when you want to start a new delete transaction, to do this
simply call the `newDeleteTransactionId()` function as shown below.

```php
<?php

use \Dddaaammmooo\TransactionalSoftDeletes\Model;
use \Dddaaammmooo\TransactionalSoftDeletes\Transaction;

class controllerName
{
    /** @var Model $softDeleteModel1 */
    private $softDeleteModel1;

    /** @var Model $softDeleteModel2 */
    private $softDeleteModel2;

    /** @var Model $softDeleteModel3 */
    private $softDeleteModel3;

    /** @var Model $softDeleteModel4 */
    private $softDeleteModel4;
           
    public function functionName()
    {
        // These models will have a delete_transaction_id of 1
        
        $this->softDeleteModel1->delete();
        $this->softDeleteModel2->delete();

        // Request a new delete_transaction_id be generated

        Transaction::newDeleteTransactionId();
        
        // These models will have a delete_transaction_id of 2
        
        $this->softDeleteModel3->delete();
        $this->softDeleteModel4->delete();
    }
}
```

### Requesting New Timestamp For Each Delete

#### Globally

There may be times when you want to keep the same transaction ID but update the timestamp of
associated records, to do this simply update the delete/restore timestamp of each
item as it is updated/stored. To do this globally `update_timestamp` to `true`.

#### Manually

Alternatively you may want to may want to manually update the timestampm you can do this by
calling the `newTimestamp()` function between deletions as shown below.

```php
<?php

use \Dddaaammmooo\TransactionalSoftDeletes\Model;
use \Dddaaammmooo\TransactionalSoftDeletes\Transaction;

class controllerName
{
    /** @var Model $softDeleteModel1 */
    private $softDeleteModel1;

    /** @var Model $softDeleteModel2 */
    private $softDeleteModel2;

    /** @var Model $softDeleteModel3 */
    private $softDeleteModel3;

    /** @var Model $softDeleteModel4 */
    private $softDeleteModel4;
           
    public function functionName()
    {
        // These models will have the same timestamp (eg. 2001-01-01 00:00:00)
        
        $this->softDeleteModel1->delete();
        sleep(5);
        $this->softDeleteModel2->delete();

        // Request a new timestamp to be used after sleeping 5 seconds

        Transaction::newTimestamp();
        
        // These models will have the same timestamp (eg. 2001-01-01 00:00:05)
        
        $this->softDeleteModel3->delete();
        sleep(5);
        $this->softDeleteModel4->delete();
    }
}
```

## Restoring Individual Deleted Items

You restore individual deleted items using the normal `Eloquent\SoftDeletes::delete`
and `Eloquent\SoftDeletes::restore` methods.

```php
<?php

    // Load a database model

    /** @var \Dddaaammmooo\TransactionalSoftDeletes\Model $$databaseModel */
    $databaseModel = Model::whereId(1)->get();
    
    // Delete it
    
    $databaseModel->delete();
    
    // Restore it
    
    $databaseModel->restore();

```

## Restoring Entire Transaction

It is possible to bulk restore all of the items that were deleted in a transaction
by calling the `Transaction::restoreTransaction($deleteTransactionId)` method.

The `$deleteTransactionId` can be retrieved from the deleted models
`delete_transaction_id` column.

```php
<?php

    // Load all models from database

    /** @var \Dddaaammmooo\TransactionalSoftDeletes\Model $databaseModel */
    $databaseModel = Model::get();
    $databaseModel->delete();
    
    // Assuming the `delete_transaction_id` value was `1`- you can restore
    // all of these deleted models by running the following command
    
    \Dddaaammmooo\TransactionalSoftDeletes\Transaction::restoreTransaction(1);

```

## Retrieving Details Of Deleted Records

You can also retrieve various information about the deleted items contained within a transaction.

### Item Count 

The `Transaction::getDeletedItemsCount($deleteTransactionId)` will provide you with the total number
of deleted items currently in the transaction requested.

```php
<?php

    use \Dddaaammmooo\TransactionalSoftDeletes\Transaction;
    
    $deleteTransactionId = 1;
    
    /** @var int $itemCount */
    $itemCount = Transaction::getDeletedItemsCount($deleteTransactionId);
```

### Item Count By Class

The `Transaction::getDeletedItemsCountByClass($deleteTransactionId)` will provide you with the
total number of deleted items currently in the transaction requested in an array that is indexed
by the model class.

For example:

```php
<?php
    use \Dddaaammmooo\TransactionalSoftDeletes\Transaction;
    
    $deleteTransactionId = 1;
    
    /** @var array $itemCountByClass */
    $itemCountByClass = Transaction::getDeletedItemsCountByClass($deleteTransactionId);
```

Would return

```
    array(2) {
        ["App\Models\Person"] => int(8)
        ["App\Models\PhoneNumber"] => int(3)
    }
```

### Deleted Model IDs/Objects By Class

The `Transaction::getDeletedItems($deleteTransactionId, $hydrate)` can be used to retrieve a list of all
deleted model ID's within a transaction in an array indexed by their type.

For example:

```php
<?php
   
    var_dump(Transaction::getDeletedItems(1));
   
```

```
    array(1) {
        ["App\Models\Person"] => array(8) {
            [0] => int(7)
            [1] => int(10)
            [2] => int(21)
            [3] => int(33)
            [4] => int(34)
            [5] => int(36)
            [6] => int(65)
            [7] => int(81)
        }
        ["App\Models\PhoneNumber"] => array(8) {
            [0] => int(15)
            [1] => int(132)
            [2] => int(223)
        }
    }
```

If the optional `$hydrate` parameter is set to true, the function will return an array containing
the actual deleted models.

```
    array(1) {
        ["App\Models\Person"] => array(8) {
            [0] => object(App\Models\Person)
            [1] => object(App\Models\Person)
            [2] => object(App\Models\Person)
            [3] => object(App\Models\Person)
            [4] => object(App\Models\Person)
            [5] => object(App\Models\Person)
            [6] => object(App\Models\Person)
            [7] => object(App\Models\Person)
        }
        ["App\Models\PhoneNumber"] => array(8) {
            [0] => object(App\Models\PhoneNumber)
            [1] => object(App\Models\PhoneNumber)
            [2] => object(App\Models\PhoneNumber)
        }
    }
```
