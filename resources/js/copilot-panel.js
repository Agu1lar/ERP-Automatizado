document.addEventListener('livewire:init', () => {
    const activeStreams = new Map();

    const findCopilot = () =>
        Livewire.all().find((item) => item.name === 'copilot.copilot-panel');

    const watchTask = (taskId) => {
        if (!taskId || activeStreams.has(taskId)) {
            return;
        }

        const url = `/api/agent/tasks/${taskId}/stream`;
        const source = new EventSource(url);

        activeStreams.set(taskId, source);

        source.addEventListener('task', (event) => {
            try {
                const data = JSON.parse(event.data);
                const component = findCopilot();
                component?.call('onTaskProgress', taskId, data);
            } catch {
                // ignore malformed payloads
            }
        });

        const closeStream = () => {
            source.close();
            activeStreams.delete(taskId);
        };

        source.addEventListener('close', closeStream);
        source.onerror = () => closeStream();
    };

    Livewire.on('copilot-watch-task', ({ taskId }) => watchTask(taskId));

    document.addEventListener('livewire:navigated', () => {
        const url = window.location.pathname + window.location.search;
        const component = findCopilot();

        if (component) {
            component.call('syncPageContext', url);
        }
    });
});
