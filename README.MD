# Transactional Record Deletion For Eloquent Models

## Requirements

* PHP >= 7.1
* Laravel >= 5.5

## Installation

First, require the library into your project

```
composer require dddaaammmooo/transactional-soft-deletes
```

Then, deploy the default migration and configuration files run the following command

```
php artisan vendor:publish 
--provider="Cartalyst\Sentinel\Laravel\SentinelServiceProvider"
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

Optionally you can create an alias for the transaction handling class by adding
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

Lastly run the migration to create the necessary deletion tables

```php
php artisan migrate
```

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

class controllerName
{
    /** @var \Dddaaammmooo\TransactionalSoftDeletes\Model */
    private $softDeleteModel1;

    /** @var \Dddaaammmooo\TransactionalSoftDeletes\Model */
    private $softDeleteModel2;

    /** @var \Dddaaammmooo\TransactionalSoftDeletes\Model */
    private $softDeleteModel3;

    /** @var \Dddaaammmooo\TransactionalSoftDeletes\Model */
    private $softDeleteModel4;
           
    public function functionName()
    {
        // These models will have a delete_transaction_id of 1
        
        $this->softDeleteModel1->delete();
        $this->softDeleteModel2->delete();

        // Request a new delete_transaction_id be generated

        \Dddaaammmooo\TransactionalSoftDeletes\Transaction::newDeleteTransactionId();
        
        // These models will have a delete_transaction_id of 2
        
        $this->softDeleteModel3->delete();
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
