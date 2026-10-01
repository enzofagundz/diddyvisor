<?php

namespace App\Mcp\Support;

/**
 * Códigos estáveis do envelope de erro das tools MCP.
 */
enum McpErrorCode: string
{
    case Validation = 'validation';
    case NotFound = 'not_found';
    case Forbidden = 'forbidden';
    case Conflict = 'conflict';
    case Precondition = 'precondition';
    case Configuration = 'configuration';
    case Internal = 'internal';
}
