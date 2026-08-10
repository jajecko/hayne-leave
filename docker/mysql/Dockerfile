FROM alpine:3.22 AS upstream
ARG JORANI_VERSION=v1.0.4
RUN apk add --no-cache curl tar \
    && mkdir -p /src \
    && curl -fsSL "https://github.com/jorani/jorani/archive/refs/tags/${JORANI_VERSION}.tar.gz" \
      | tar -xz --strip-components=1 -C /src

FROM mysql:8.0
COPY --from=upstream /src/legacy/sql/init/ /docker-entrypoint-initdb.d/
