<?php

// i18n: Self-help knowledge base module (list, detail page, create/edit forms).
// Keep the keys identical to lang/zh_TW/knowledge_base.php when editing.
return [
    'index_title' => 'Knowledge Base List',
    'index_heading' => 'Self-Help Troubleshooting Knowledge Base',
    'add_entry' => 'Add Entry',
    'empty_list' => 'There are no knowledge base entries yet.',

    'table' => [
        'title' => 'Title',
        'category' => 'Category',
        'status' => 'Status',
        'updated_at' => 'Updated At',
        'actions' => 'Actions',
    ],

    'uncategorized' => 'Uncategorized',
    'published' => 'Published',
    'unpublished' => 'Unpublished',
    'confirm_delete' => 'Are you sure you want to delete ":title"?',

    'create_title' => 'Add Knowledge Base Entry',
    'edit_title' => 'Edit Knowledge Base Entry',

    'form' => [
        'title' => 'Title',
        'category' => 'Category (optional, e.g. Projector / Computer / Network)',
        'symptom' => 'Common Symptoms',
        'solution' => 'Self-Help Steps',
        'is_published' => 'Publish (visible to users)',
    ],

    'show' => [
        'category_prefix' => 'Category: ',
        'status_prefix' => 'Status: ',
        'symptom_heading' => 'Common Symptoms',
        'solution_heading' => 'Self-Help Steps',
        'resolved_prompt' => 'Did following the steps above solve the problem?',
        'resolved_button' => 'Issue Resolved',
        'unresolved_button' => "Couldn't Fix It, Submit a Repair Request",
    ],

    'flash' => [
        'created' => 'Knowledge base entry added.',
        'updated' => 'Knowledge base entry updated.',
        'deleted' => 'Knowledge base entry deleted.',
        'resolved_message' => 'Great, glad ":title" solved your problem!',
    ],
];
