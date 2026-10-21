<?php
require_once __DIR__ . '/Mcp_reply.php';

/**
 * One tool an MCP server offers. A module writes one class per tool next to
 * its controller, and passes what the tool needs (the user, the search) to
 * its constructor:
 *
 *   class Add_job_links_tool extends Mcp_tool {
 *       public function name(): string { return 'add_job_links'; }
 *       public function description(): string { return 'Adds job posts to ...'; }
 *       public function schema(): array { return ['type' => 'object', ...]; }
 *       public function handle(array $arguments): Mcp_reply { ... }
 *   }
 *
 * handle() gets arguments already checked against schema() (Mcp_schema), so
 * the schema the client sees and the check the server runs are the same.
 */
abstract class Mcp_tool {

    /** The name the client calls, such as add_job_links. */
    abstract public function name(): string;

    /** What the tool does, for the model to read when it picks a tool. */
    abstract public function description(): string;

    /** The arguments as a JSON Schema object (the subset Mcp_schema checks). */
    abstract public function schema(): array;

    abstract public function handle(array $arguments): Mcp_reply;

    /** A name for people, shown by some clients; '' leaves it out. */
    public function title(): string {
        return '';
    }

    /** Hints such as readOnlyHint and destructiveHint; [] leaves them out. */
    public function annotations(): array {
        return [];
    }

    /** The tool as tools/list describes it. */
    public function definition(): array {
        $definition = ['name' => $this->name()];
        if ($this->title() !== '') {
            $definition['title'] = $this->title();
        }
        $definition['description'] = $this->description();
        $definition['inputSchema'] = $this->schema();
        if ($this->annotations() !== []) {
            $definition['annotations'] = $this->annotations();
        }
        return $definition;
    }

}
