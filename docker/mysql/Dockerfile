FROM alpine:3.22 AS upstream
ARG JORANI_VERSION=v1.0.4
RUN set -eux; \
    apk add --no-cache git; \
    fetched=0; \
    for attempt in 1 2 3 4 5; do \
      rm -rf /src; \
      if GIT_TERMINAL_PROMPT=0 git -c http.version=HTTP/1.1 clone \
        --depth 1 \
        --branch "${JORANI_VERSION}" \
        --single-branch \
        https://github.com/jorani/jorani.git /src; then \
        fetched=1; \
        break; \
      fi; \
      sleep $((attempt * 2)); \
    done; \
    test "$fetched" = "1"; \
    rm -rf /src/.git

FROM mysql:8.0
COPY --from=upstream /src/legacy/sql/init/ /docker-entrypoint-initdb.d/
COPY hayne/sql/001-leave-profiles.sql /docker-entrypoint-initdb.d/zz-hayne-leave-profiles.sql
COPY hayne/sql/002-ad-identity.sql /docker-entrypoint-initdb.d/zzz-hayne-ad-identity.sql
COPY hayne/sql/003-leave-type-registry.sql /docker-entrypoint-initdb.d/zzzz-hayne-leave-type-registry.sql
