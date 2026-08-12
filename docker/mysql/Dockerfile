FROM alpine:3.22 AS upstream
ARG JORANI_VERSION=v1.0.4
RUN apk add --no-cache curl tar \
    && mkdir -p /src \
    && curl -fL \
      --retry 5 \
      --retry-delay 2 \
      --retry-all-errors \
      -o /tmp/jorani.tar.gz \
      "https://github.com/jorani/jorani/archive/refs/tags/${JORANI_VERSION}.tar.gz" \
    && tar -xzf /tmp/jorani.tar.gz --strip-components=1 -C /src \
    && rm -f /tmp/jorani.tar.gz

FROM mysql:8.0
COPY --from=upstream /src/legacy/sql/init/ /docker-entrypoint-initdb.d/
COPY hayne/sql/001-leave-profiles.sql /docker-entrypoint-initdb.d/zz-hayne-leave-profiles.sql
COPY hayne/sql/002-ad-identity.sql /docker-entrypoint-initdb.d/zzz-hayne-ad-identity.sql
