document.addEventListener('livewire:init', () => {
    document.addEventListener('livewire:navigated', () => {
        const url = window.location.pathname + window.location.search;
        const component = Livewire.all().find((item) => item.name === 'copilot.copilot-panel');

        if (component) {
            component.call('syncPageContext', url);
        }
    });
});
