<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource;

enum QueryType: string
{
    case CREATE = 'create';
    case READ = 'read';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
