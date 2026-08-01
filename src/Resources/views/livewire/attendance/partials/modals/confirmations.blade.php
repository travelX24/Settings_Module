<div>
    {{-- System Confirmation Dialog for Deletion --}}
    <x-ui.confirm-dialog 
        id="delete-location"
        title="{{ tr('Remove Location?') }}"
        message="{{ tr('Are you sure you want to remove this geographic location? This action cannot be undone.') }}"
        confirmText="{{ tr('Yes, Remove') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deleteGpsLocation(__ID__)"
        type="danger"
    />

    <x-ui.confirm-dialog 
        id="delete-penalty"
        title="{{ tr('Delete Policy?') }}"
        message="{{ tr('Are you sure you want to delete this penalty policy? This will stop applying its rules to violations.') }}"
        confirmText="{{ tr('Yes, Delete') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deletePenalty(__ID__)"
        type="danger"
    />

    <x-ui.confirm-dialog 
        id="delete-absence"
        title="{{ tr('Delete Absence Rule?') }}"
        message="{{ tr('Are you sure you want to delete this absence policy? Unapproved absences will no longer trigger this penalty.') }}"
        confirmText="{{ tr('Yes, Delete') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deleteAbsencePolicy(__ID__)"
        type="danger"
    />

    <x-ui.confirm-dialog 
        id="delete-group"
        title="{{ tr('Delete Group?') }}"
        message="{{ tr('Are you sure you want to delete this group? All employee assignments and custom policies will be lost.') }}"
        confirmText="{{ tr('Yes, Delete') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deleteGroup(__ID__)"
        type="danger"
    />

    <x-ui.confirm-dialog 
        id="delete-device"
        title="{{ tr('Remove Device?') }}"
        message="{{ tr('Are you sure you want to remove this device? It will no longer be able to record attendance.') }}"
        confirmText="{{ tr('Yes, Remove') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deleteDevice(__ID__)"
        type="danger"
    />

    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: #f9fafb; }
        ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
    </style>
</div>
