<?php

// i18n: User master (account management). Keep the keys identical to lang/zh_TW/users.php.
return [
    'index_title' => 'User Master',
    'add_user' => 'Add User',
    'empty_list' => 'No users match the current filter.',
    'never_logged_in' => 'Never logged in',
    'current_user_badge' => 'You',
    'no_department' => 'Unassigned',

    'filter' => [
        'keyword' => 'Keyword',
        'keyword_placeholder' => 'Username / name / email / phone',
        'role' => 'Role',
        'role_all' => 'All',
        'department' => 'Department',
        'department_all' => 'All',
        'status' => 'Status',
        'status_all' => 'All',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'deleted' => 'Deleted',
    ],

    'table' => [
        'username' => 'Username',
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'role' => 'Role',
        'department' => 'Department',
        'status' => 'Status',
        'last_login_at' => 'Last Login',
        'actions' => 'Actions',
    ],

    'actions' => [
        'activate' => 'Activate account',
        'deactivate' => 'Deactivate account',
        'restore' => 'Restore account',
        'reset_password' => 'Reset password',
    ],

    'confirm' => [
        'deactivate' => 'Deactivate ":name"? They will no longer be able to log in and any active sessions will be signed out.',
        'delete' => 'Delete ":name"? The account is marked as deleted (it can be restored) and any active sessions are signed out.',
        'restore' => 'Restore ":name"?',
    ],

    'form' => [
        'create_title' => 'Add User',
        'edit_title' => 'Edit User',
        'section_account' => 'Account',
        'section_contact' => 'Contact & Affiliation',
        'section_password' => 'Login Password',
        'section_reset_password' => 'Reset Password',
        'username' => 'Username',
        'username_hint' => '3-30 characters: letters, digits, dot, underscore or hyphen. Can be changed later.',
        'name' => 'Name',
        'email' => 'Email',
        'email_hint' => 'Dispatch notification emails are sent to this address.',
        'phone' => 'Phone',
        'role' => 'Role',
        'role_placeholder' => 'Select a role',
        'department' => 'Department',
        'department_placeholder' => 'Unassigned',
        'is_active' => 'Account active (turn off to block login)',
        'password' => 'Password',
        'password_hint' => 'At least 8 characters.',
        'password_confirmation' => 'Confirm Password',
        'new_password' => 'New Password',
        'reset_password_hint' => "The user's active sessions are signed out and they must log in again with the new password.",
        'created_at' => 'Created At',
        'last_login_at' => 'Last Login',
    ],

    'flash' => [
        'created' => 'User added.',
        'updated' => 'User updated.',
        'activated' => 'Account activated.',
        'deactivated' => 'Account deactivated.',
        'password_reset' => 'Password reset.',
        'deleted' => 'User deleted (use the "Deleted" filter to restore).',
        'restored' => 'User restored.',
    ],

    'errors' => [
        'self_protected' => 'You cannot delete or deactivate your own account, or change your own role.',
        'last_admin' => 'The system must keep at least one active administrator; this account cannot be deleted, deactivated or demoted.',
    ],
];
