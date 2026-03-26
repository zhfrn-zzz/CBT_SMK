export type SettingType = 'string' | 'boolean' | 'integer' | 'json' | 'file';

export type SettingsGrouped = {
    general: Record<string, string | number | boolean | null>;
    appearance: Record<string, string | number | boolean | null>;
    exam: Record<string, string | number | boolean | null>;
    email: Record<string, string | number | boolean | null>;
};

export type SettingsPageProps = {
    settings: SettingsGrouped;
};
