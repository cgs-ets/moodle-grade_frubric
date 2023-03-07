import { defineConfig } from "cypress";

export default defineConfig({
    e2e: {
        setupNodeEvents(on, config) {
        },
        // implement node event listeners here
        baseUrl: 'http://localhost/cgsconnect', // Local instance
        // experimentalSessionAndOrigin: true, // Allow login remember
        watchForFileChanges: false, // Do not auto re-run tests every time code changes.
        env: {
            username: 'admin',
            password: 'Veronica11#',
        }
    },

    },
);
