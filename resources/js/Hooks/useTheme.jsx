import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

const STORAGE_KEY = 'eec-theme';
const THEMES = ['light', 'dark', 'system'];

const ThemeContext = createContext(null);

function getStoredTheme() {
    if (typeof window === 'undefined') {
        return 'system';
    }

    const stored = window.localStorage.getItem(STORAGE_KEY);

    return THEMES.includes(stored) ? stored : 'system';
}

function systemPrefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolveIsDark(theme) {
    return theme === 'dark' || (theme === 'system' && systemPrefersDark());
}

function applyTheme(theme) {
    const isDark = resolveIsDark(theme);
    const root = document.documentElement;

    root.classList.toggle('dark', isDark);
    root.style.colorScheme = isDark ? 'dark' : 'light';

    return isDark;
}

export function ThemeProvider({ children }) {
    const [theme, setThemeState] = useState(getStoredTheme);
    const [isDark, setIsDark] = useState(() => resolveIsDark(getStoredTheme()));

    useEffect(() => {
        setIsDark(applyTheme(theme));
    }, [theme]);

    useEffect(() => {
        if (theme !== 'system') {
            return undefined;
        }

        const query = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => setIsDark(applyTheme('system'));

        query.addEventListener('change', handleChange);

        return () => query.removeEventListener('change', handleChange);
    }, [theme]);

    const setTheme = useCallback((nextTheme) => {
        if (!THEMES.includes(nextTheme)) {
            return;
        }

        window.localStorage.setItem(STORAGE_KEY, nextTheme);
        setThemeState(nextTheme);
    }, []);

    const value = useMemo(
        () => ({
            theme,
            isDark,
            setTheme,
            toggleTheme: () => setTheme(isDark ? 'light' : 'dark'),
        }),
        [theme, isDark, setTheme],
    );

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
    const context = useContext(ThemeContext);

    if (!context) {
        throw new Error('useTheme must be used inside a ThemeProvider.');
    }

    return context;
}
