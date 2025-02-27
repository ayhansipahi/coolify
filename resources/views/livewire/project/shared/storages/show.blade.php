<div>
    <x-modal yesOrNo modalId="{{ $modalId }}" modalTitle="Delete Storage">
        <x-slot:modalBody>
            <p>This storage will be deleted <span class="font-bold text-warning">({{ $storage->name }})</span>. It is
                not
                reversible. <br>Please think again.</p>
        </x-slot:modalBody>
    </x-modal>

    <form wire:submit='submit' class="flex flex-col gap-2 xl:items-end xl:flex-row">
        @if ($isReadOnly)
            @if ($isFirst)
                <x-forms.input id="storage.name" label="Volume Name" required readonly />
                <x-forms.input id="storage.host_path" label="Source Path (on host)" readonly />
                <x-forms.input id="storage.mount_path" label="Destination Path (in container)" required readonly />
            @else
                <x-forms.input id="storage.name" required readonly />
                <x-forms.input id="storage.host_path" readonly />
                <x-forms.input id="storage.mount_path" required readonly />
            @endif
        @else
            @if ($isFirst)
                <x-forms.input id="storage.name" label="Volume Name" required />
                <x-forms.input id="storage.host_path" label="Source Path (on host)" />
                <x-forms.input id="storage.mount_path" label="Destination Path (in container)" required />
            @else
                <x-forms.input id="storage.name" required />
                <x-forms.input id="storage.host_path" />
                <x-forms.input id="storage.mount_path" required />
            @endif
            <div class="flex gap-2">
                <x-forms.button type="submit">
                    Update
                </x-forms.button>
                <x-forms.button isError isModal modalId="{{ $modalId }}">
                    Delete
                </x-forms.button>
            </div>
        @endif
    </form>
</div>
