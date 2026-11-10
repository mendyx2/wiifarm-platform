# Dependency register

Every outside package the platform uses directly, with its licence. Only licences that allow
commercial use are accepted (MIT, BSD, Apache-2.0, ISC). Add a line here in the same change
that adds a package. Regenerate the versions with the commands at the bottom.

## Back end (Composer)

| Package | Version | Licence | Used for |
| --- | --- | --- | --- |
| `inertiajs/inertia-laravel` | v2.0.28 | MIT | Serves React pages from Laravel |
| `laravel/framework` | v12.71.0 | MIT | The application framework |
| `laravel/tinker` | v2.11.1 | MIT | Command-line console |
| `tightenco/ziggy` | v2.6.5 | MIT | Laravel route names in the front end |
| `fakerphp/faker` (development only) | v1.24.1 | MIT | Development tooling |
| `laravel/pail` (development only) | v1.2.7 | MIT | Development tooling |
| `laravel/pint` (development only) | v1.32.1 | MIT | Development tooling |
| `laravel/sail` (development only) | v1.69.0 | MIT | Development tooling |
| `mockery/mockery` (development only) | 1.6.15 | BSD-3-Clause | Development tooling |
| `nunomaduro/collision` (development only) | v8.9.5 | MIT | Development tooling |
| `phpunit/phpunit` (development only) | 11.5.57 | BSD-3-Clause | Development tooling |

## Front end (npm)

| Package | Version | Licence |
| --- | --- | --- |
| `@headlessui/react` | 2.2.0 | MIT |
| `@inertiajs/react` | 2.0.3 | MIT |
| `@radix-ui/react-avatar` | 1.1.3 | MIT |
| `@radix-ui/react-checkbox` | 1.1.4 | MIT |
| `@radix-ui/react-collapsible` | 1.1.3 | MIT |
| `@radix-ui/react-dialog` | 1.1.6 | MIT |
| `@radix-ui/react-dropdown-menu` | 2.1.6 | MIT |
| `@radix-ui/react-label` | 2.1.2 | MIT |
| `@radix-ui/react-navigation-menu` | 1.2.5 | MIT |
| `@radix-ui/react-select` | 2.1.6 | MIT |
| `@radix-ui/react-separator` | 1.1.2 | MIT |
| `@radix-ui/react-slot` | 1.1.2 | MIT |
| `@radix-ui/react-toggle` | 1.1.2 | MIT |
| `@radix-ui/react-toggle-group` | 1.1.2 | MIT |
| `@radix-ui/react-tooltip` | 1.1.8 | MIT |
| `@tailwindcss/vite` | 4.0.8 | MIT |
| `@types/react` | 19.0.10 | MIT |
| `@types/react-dom` | 19.0.4 | MIT |
| `@vitejs/plugin-react` | 4.3.4 | MIT |
| `class-variance-authority` | 0.7.1 | Apache-2.0 |
| `clsx` | 2.1.1 | MIT |
| `concurrently` | 9.1.2 | MIT |
| `globals` | 15.15.0 | MIT |
| `laravel-vite-plugin` | 1.2.0 | MIT |
| `lucide-react` | 0.475.0 | ISC |
| `react` | 19.0.0 | MIT |
| `react-dom` | 19.0.0 | MIT |
| `tailwind-merge` | 3.0.2 | MIT |
| `tailwindcss` | 4.0.8 | MIT |
| `tailwindcss-animate` | 1.0.7 | MIT |
| `typescript` | 5.7.3 | Apache-2.0 |
| `vite` | 6.1.1 | MIT |
| `@eslint/js` (development only) | 9.21.0 | MIT |
| `eslint` (development only) | 9.21.0 | MIT |
| `eslint-config-prettier` (development only) | 10.0.1 | MIT |
| `eslint-plugin-react` (development only) | 7.37.4 | MIT |
| `eslint-plugin-react-hooks` (development only) | 5.1.0 | MIT |
| `prettier` (development only) | 3.5.2 | MIT |
| `prettier-plugin-organize-imports` (development only) | 4.1.0 | MIT |
| `prettier-plugin-tailwindcss` (development only) | 0.6.11 | MIT |
| `typescript-eslint` (development only) | 8.24.1 | MIT |

**Starting point:** the project began from Laravel's official React starter kit
(`laravel/react-starter-kit`, MIT licence), taken on 10 November 2026.

**Check:** `composer licenses` and the `license` field of each package in `node_modules`.
