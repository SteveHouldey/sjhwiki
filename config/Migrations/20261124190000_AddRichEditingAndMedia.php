<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddRichEditingAndMedia extends BaseMigration
{
    /**
     * Add content formats, protected media metadata, and an ordered Git queue.
     *
     * @return void
     */
    public function up(): void
    {
        if (!$this->table('wiki_pages')->hasColumn('content_format')) {
            $this->execute("ALTER TABLE wiki_pages ADD COLUMN content_format VARCHAR(16) NOT NULL DEFAULT 'markdown'");
        }
        if (!$this->table('wiki_page_revisions')->hasColumn('content_format')) {
            $this->execute(
                "ALTER TABLE wiki_page_revisions ADD COLUMN content_format VARCHAR(16) NOT NULL DEFAULT 'markdown'",
            );
        }

        if (!$this->hasTable('wiki_media')) {
            $this->table('wiki_media')
            ->addColumn('wiki_id', 'integer')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('sha256', 'string', ['limit' => 64])
            ->addColumn('extension', 'string', ['limit' => 8])
            ->addColumn('mime_type', 'string', ['limit' => 64])
            ->addColumn('file_size', 'integer')
            ->addColumn('created', 'datetime')
            ->addColumn('git_synced', 'boolean', ['default' => false])
            ->addColumn('git_error', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('git_commit', 'string', ['limit' => 64, 'null' => true])
            ->addIndex(['wiki_id', 'sha256'], ['unique' => true])
            ->addForeignKey('wiki_id', 'wikis', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('wiki_git_queue')) {
            $this->table('wiki_git_queue')
            ->addColumn('kind', 'string', ['limit' => 16])
            ->addColumn('entity_id', 'integer')
            ->addColumn('git_commit', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('git_error', 'string', ['limit' => 255, 'null' => true])
            ->addIndex(['kind', 'entity_id'], ['unique' => true])
                ->create();
        }

        $this->execute(
            "INSERT INTO wiki_git_queue (kind, entity_id, git_commit, git_error)
             SELECT 'revision', r.id, r.git_commit, r.git_error FROM wiki_page_revisions r
             WHERE r.git_synced = 0 AND NOT EXISTS
                 (SELECT 1 FROM wiki_git_queue q WHERE q.kind = 'revision' AND q.entity_id = r.id)
             ORDER BY r.id",
        );
    }

    /**
     * Prevent rollback from deleting uploaded assets or rich content.
     *
     * @return void
     */
    public function down(): void
    {
        throw new RuntimeException('This migration cannot be rolled back without losing rich content or media.');
    }
}
