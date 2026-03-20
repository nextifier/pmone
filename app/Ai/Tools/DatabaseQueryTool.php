<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DatabaseQueryTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Execute a read-only SQL query against the PostgreSQL database to retrieve data and generate insights. Only SELECT statements are allowed. Always use LIMIT to keep results manageable (max 50 rows). For counting, use COUNT(). For aggregation, use GROUP BY with appropriate aggregate functions.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = trim($request->string('query'));

        // Strip trailing semicolons
        $query = rtrim($query, '; ');

        // Validate: only SELECT queries allowed
        if (! preg_match('/^\s*SELECT\b/i', $query)) {
            return 'Error: Only SELECT queries are allowed. Use SELECT to read data from the database.';
        }

        // Block dangerous statements
        $blocked = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'GRANT', 'REVOKE', 'EXEC'];
        foreach ($blocked as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/i', $query)) {
                return "Error: {$keyword} statements are not allowed. Only SELECT queries are permitted.";
            }
        }

        // Enforce LIMIT if not present
        if (! preg_match('/\bLIMIT\b/i', $query)) {
            $query .= ' LIMIT 50';
        }

        try {
            $results = DB::select($query);

            if (empty($results)) {
                return 'Query returned no results.';
            }

            $count = count($results);

            return "Query returned {$count} row(s):\n\n".json_encode($results, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return 'Query error: '.$e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The SQL SELECT query to execute. Must start with SELECT. Always include LIMIT clause (max 50).')
                ->required(),
        ];
    }
}
