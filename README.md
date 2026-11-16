# oezCMS

## About

This project is a complete rewrite of my previous Content Management System (CMS).

The original CMS has been developed and maintained over many years. Instead of continuing to extend the existing codebase, I decided to rebuild the entire application from scratch using modern software architecture, current security standards, and best development practices.

This project is being developed with the active assistance of Artificial Intelligence (AI). AI is used as a development assistant for architecture design, code reviews, documentation, brainstorming, and implementation support. Every contribution generated with AI is carefully reviewed, tested, and adapted before becoming part of the project.

The primary goal is to create a secure, maintainable, extensible, and modern CMS that serves as a solid foundation for future development while remaining completely open source.

## Project Goals

* Modern PHP architecture
* MariaDB database backend
* Secure authentication and authorization (RBAC)
* Multi-language support (i18n)
* Responsive and accessible user interface
* Secure coding practices based on OWASP recommendations
* Clean, maintainable, and well-documented source code
* Transparent and community-driven open-source development

## Open Source

This project is developed as an open-source project from the very beginning.

Source code, architecture decisions, documentation, and development progress are published publicly. Contributions, suggestions, bug reports, feature requests, and pull requests are welcome.

The objective is to build a high-quality CMS together with the open-source community.

## AI-Assisted Development

Artificial Intelligence is used as a development assistant throughout this project.

AI assists with:

* Software architecture discussions
* Database design
* Security reviews
* Code generation
* Refactoring
* Documentation
* Brainstorming and problem solving

AI is a supporting development tool. All code is reviewed, tested, and validated before it becomes part of the project.

## Project Status

🚧 **Under active development — not usable as a CMS yet.** Current version: `0.1.0-dev`.

The foundation is built before the features that depend on it. What exists today:

* **Runtime core** — strict `.env` parser, validated configuration, PSR-11 container, kernel
* **Database layer** — PDO wrapper with nested transactions (savepoints) and stored procedure calls; TCP or socket connections, optional TLS
* **Schema deployment** — `bin/console db:deploy` with tracked, checksummed migrations, a deploy lock and an optional separate migration account
* **Internationalisation** — locales with fallback chains, translation keys and values; translations are resolved inside the database
* **Quality gates** — PSR-12, PHPStan level 10, PHPUnit unit and integration suites against MariaDB 11, an architecture test for the layer boundaries

Not built yet: the HTTP layer (there is no web entry point), users, authentication and RBAC, media, and the plugin system.

## Documentation

Start at [docs/README.md](docs/README.md). What comes next is in the [roadmap](docs/project/roadmap.md).

## License

Copyright (C) 2026 Mehmet Özbilen

oezCMS is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version** (SPDX: `GPL-3.0-or-later`).

oezCMS is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

The full text of version 3 is in the `LICENSE` file. Contributions are accepted under the same license.

## Contributing

Contributions are welcome.

If you would like to contribute, please open an issue to discuss your ideas or submit a pull request. Every contribution—whether it is code, documentation, testing, or security improvements—is appreciated.
