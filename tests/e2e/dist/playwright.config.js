"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.baseConfig = void 0;
exports.makeConfig = makeConfig;
const test_1 = require("@playwright/test");
const node_path_1 = __importDefault(require("node:path"));
const node_fs_1 = __importDefault(require("node:fs"));
const storagePath_1 = require("./utils/storagePath");
const storageStatePath = (0, storagePath_1.getStorageStatePath)();
node_fs_1.default.mkdirSync(node_path_1.default.dirname(storageStatePath), { recursive: true });
const baseSetupPath = require.resolve('./utils/globalSetup');
const baseTeardownPath = require.resolve('./utils/globalTeardown');
const base = {
    globalSetup: [baseSetupPath],
    globalTeardown: [baseTeardownPath],
    testDir: './tests',
    testMatch: '**/*.ts',
    timeout: 300000,
    expect: { timeout: 5000 },
    fullyParallel: true,
    retries: process.env.CI ? 2 : 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.BASE_URL ?? 'https://www.test.hel.ninja/',
        storageState: storageStatePath,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        viewport: { width: 1280, height: 800 },
        launchOptions: { slowMo: process.env.SLOWMO ? 1000 : 0 },
    },
    projects: [
        { name: 'chromium', use: { ...test_1.devices['Desktop Chrome'] } },
    ],
};
exports.baseConfig = (0, test_1.defineConfig)(base);
const toArray = (v) => v ? (Array.isArray(v) ? v : [v]) : [];
const mergeAppend = (baseVal, overrideVal) => Array.from(new Set([...toArray(baseVal), ...toArray(overrideVal)]));
function makeConfig(overrides = {}) {
    const merged = {
        ...base,
        ...overrides,
        use: { ...base.use, ...(overrides.use ?? {}) },
        globalSetup: mergeAppend(base.globalSetup, overrides.globalSetup),
        globalTeardown: mergeAppend(base.globalTeardown, overrides.globalTeardown),
    };
    return (0, test_1.defineConfig)(merged);
}
exports.default = exports.baseConfig;
