# PR-LOGIN-01 implementation note

Branch: `feat/pr-login-01-centered-login-brand-lockup`
Base: `main`
Base commit: `3f45abb166f0964c60e7af3fc7038eacea739b74`

Implementation is intentionally presentation-only. The supplied HAYNE + LEAVE SVG replaces the previous HAYNE-only logo asset, while separately rendered Leave labels are removed from menu/login/failure branding. Login JS no longer reconstructs the LEAVE submark or duplicate product heading. Login CSS centers a 440px card and removes the desktop split panel.

Verification is delegated to `verify` plus `verify-pr-login-01`, including independent patch dry-runs, built-image guards, rendered DOM checks and desktop/mobile screenshots.
