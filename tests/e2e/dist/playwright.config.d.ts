import { defineConfig } from '@playwright/test';
type Config = Parameters<typeof defineConfig>[0];
export declare const baseConfig: import("@playwright/test").PlaywrightTestConfig<{}, {}>;
export declare function makeConfig(overrides?: Partial<Config>): import("@playwright/test").PlaywrightTestConfig<{}, {}>;
export default baseConfig;
