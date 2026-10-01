/** Tema claro / oscuro / sistema. La preferencia se guarda en el usuario. */
export const theme = {
    apply(mode) {
        const dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.dataset.theme = mode;
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: { mode, dark } }));
    },
    async set(mode) {
        this.apply(mode);
        try {
            await window.api('/perfil/tema', { method: 'POST', body: { theme: mode } });
        } catch (e) {}
    },
};
