<?php

declare(strict_types=1);

return [
    'example_site_data_command_missing' => 'Example site data command is not registered.',
    'example_site_data_environment_blocked' => 'Example site data can only be installed from the admin panel in local or testing environments.',
    'example_site_data_installation_failed' => 'Example site data installation failed.',
    'example_site_data_installed' => 'Example site data installed successfully.',
    'example_site_data_queued' => 'Example site data generation queued.',
    'example_site_data_stalled' => 'The previous generation stopped without completing. Start a new run to try again.',
    'generation_collision' => 'Generation cannot be queued while an ordinary site has the same name. Choose another site name.',
    'generation_in_progress' => 'Another Demo Kit generation is already in progress.',
    'manage_permission_required' => 'Demo Kit generation requires extension-management permission.',
    'plan_generation' => 'Plan demo generation',
    'plan_generation_description' => 'Review the exact content plan before anything is queued. The recommended profile uses the deterministic example-site defaults.',
    'plan_generation_heading' => 'Plan demo generation',
    'profile' => 'Generation profile',
    'profile_custom' => 'Custom generation',
    'profile_recommended' => 'Recommended example site',
    'queue_confirmation_required' => 'Explicit confirmation is required before queuing demo generation.',
    'queue_generation' => 'Queue reviewed generation',
    'queue_generation_description' => 'Queue :sites site(s), :languages site-language pair(s), :pages page(s), and :media media item(s)? Fingerprint: :fingerprint',
    'queue_generation_heading' => 'Confirm demo generation',
    'reset_demo_sites' => 'Reset generated sites',
    'reset_demo_sites_description' => 'This removes only sites marked as provisioned by Demo Kit. Ordinary sites are never selected or deleted.',
    'reset_demo_sites_field' => 'Provisioned Demo Kit sites',
    'reset_demo_sites_heading' => 'Confirm provenance-only reset',
    'review_changed' => 'The generation plan changed. Review it again before queuing.',
    'review_ready' => 'Generation plan ready for review.',
    'review_required' => 'A reviewed generation plan is required before queuing.',
];
