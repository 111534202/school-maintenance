<?php

// i18n: Repair request module (board, create form, detail page: dispatch/start/
// accept/reject/reassign). Keep the keys identical to lang/zh_TW/repair_requests.php.
return [
    'board_title' => 'Repair Request Board',
    'add_request' => '+ New Repair Request',
    'empty_list' => 'No repair requests match the current filter.',

    'filter' => [
        'status' => 'Status',
        'status_all' => 'All',
        'location' => 'Classroom / Location',
        'location_placeholder' => 'e.g. A101',
        'assignee' => 'Technician',
        'assignee_placeholder' => 'e.g. John Smith',
    ],

    'table' => [
        'title' => 'Title',
        'device_location' => 'Device / Location',
        'impact_level' => 'Impact Level',
        'affects_class' => 'Affects Class',
        'status' => 'Status',
        'assignee' => 'Technician',
        'submitted_at' => 'Submitted At',
        'waiting_time' => 'Waiting Time',
    ],

    'unassigned' => 'Unassigned',
    'active_case_suffix' => '(:count open case(s))',
    'not_filled' => 'Not filled in',
    'yes' => 'Yes',
    'no' => 'No',

    'impact_level' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ],

    'status' => [
        'pending' => 'New',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'pending_review' => 'Pending Review',
        'completed' => 'Completed',
    ],

    'create' => [
        'title' => 'New Repair Request',
        'from_kb_notice' => 'Continuing from knowledge base article ":title" — the steps above did not solve it. Please fill in the details below to submit a repair request.',
        'title_label' => 'Request Title',
        'device_code_label' => 'Device Barcode / Code (scan or type it in)',
        'device_code_placeholder' => 'e.g. DEV-A101-01',
        'device_code_hint' => 'Scanning or typing a device code auto-fills the device info below — no need to describe the device manually.',
        'device_lookup_error' => "Couldn't find that device code — check the barcode or describe the device in the text field below instead.",
        'device_display_label' => 'Scanned device: ',
        'device_note_label' => 'Device Location / Description (e.g. Classroom A101 projector; skip if a device was already scanned above)',
        'device_note_hint' => "If you didn't scan a device code, describe the device location here — if you did, the real device record is used automatically.",
        'location_label' => 'Location (optional)',
        'description_label' => 'Issue Description',
        'description_prefill' => "Already tried the steps from \":title\", still not resolved:\n",
        'impact_level_label' => 'Impact Level',
        'affects_class_label' => 'Currently affecting class',
        'attachments_label' => 'Photos/Videos of the issue (optional, up to 5 files, jpg/png/pdf/mp4/mov/webm, max 20MB each)',
        'submit' => 'Submit Repair Request',
    ],

    'show' => [
        'back_to_board' => 'Back to Board',
        'device_location_prefix' => 'Device / Location: ',
        'impact_level_prefix' => 'Impact Level: ',
        'affects_class_prefix' => 'Affects Class: ',
        'status_prefix' => 'Status: ',
        'assignee_prefix' => 'Technician: ',
        'scheduled_suffix' => '(scheduled for :datetime)',
        'submitted_prefix' => 'Submitted At: ',
        'last_rejection_reason_prefix' => 'Last Rejection Reason: ',
        'description_heading' => 'Issue Description',
        'attachments_heading' => 'Attachments',

        'dispatch_heading' => 'Dispatch',
        'assignee_field_label' => 'Technician',
        'assignee_field_placeholder' => 'Select a technician',
        'scheduled_field_label' => 'Scheduled Date (optional)',
        'confirm_dispatch' => 'Confirm Dispatch',
        'start_processing' => 'Start Processing',
        'fill_repair_log' => 'Fill In Repair Log',

        'reassign_summary' => 'Reassign Technician',
        'reassign_to_label' => 'Reassign To (Technician)',
        'confirm_reassign' => 'Confirm Reassign',

        'acceptance_heading' => 'Acceptance',
        'accept_pass' => 'Accept & Close Case',
        'accept_fail_summary' => 'Reject & Send Back for Rework',
        'rejection_reason_label' => 'Rejection Reason (visible to the technician, please be specific)',
        'confirm_reject' => 'Confirm Reject',

        'repair_logs_heading' => 'Repair Logs',
        'processing_time_prefix' => 'Processing Time: ',
        'total_hours_suffix' => '(:hours hours total)',
        'cause_prefix' => 'Cause: ',
        'resolution_prefix' => 'Resolution: ',
        'parts_used_prefix' => 'Parts Used: ',
        'log_attachments_prefix' => 'Before/After Photos/Videos: ',
    ],

    'flash' => [
        'submitted' => 'Repair request submitted.',
        'dispatched' => 'Dispatched.',
        'reassigned' => 'Reassigned.',
        'started' => 'Marked as in progress.',
        'completed' => 'Accepted and closed.',
        'rejected' => 'Sent back for rework.',
    ],

    'errors' => [
        'invalid_transition' => 'Cannot change the case from ":from" to ":to" — that is not an allowed status transition.',
        'reassign_invalid_status' => 'The case status is ":status", not "Assigned" or "In Progress" — it cannot be reassigned.',
    ],
];
