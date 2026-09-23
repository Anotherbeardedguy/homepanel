#!/bin/sh
set -eu

if [ "$#" -lt 1 ]; then
    echo "Käyttö: sh docker/nginx/issue-local-cert.sh <nimi-tai-ip> [lisänimi...]" >&2
    echo "Esimerkki: sh docker/nginx/issue-local-cert.sh 192.0.2.10" >&2
    exit 1
fi

directory=$(CDPATH= cd -- "$(dirname "$0")" && pwd)/certs
mkdir -p "$directory"

if [ -f "$directory/server.crt" ] && [ -f "$directory/server.key" ] && [ -f "$directory/ca.crt" ]; then
    exit 0
fi

san="IP:127.0.0.1,DNS:localhost"
for name in "$@"; do
    case "$name" in
        *[!A-Za-z0-9.:-]*)
            echo "Nimi sisältää merkin, jota varmenteeseen ei hyväksytä: $name" >&2
            exit 1
            ;;
    esac

    case "$name" in
        *:*)
            san="$san,IP:$name"
            ;;
        *.*.*.*)
            if printf '%s' "$name" | grep -Eq '^[0-9]{1,3}(\.[0-9]{1,3}){3}$'; then
                san="$san,IP:$name"
            else
                san="$san,DNS:$name"
            fi
            ;;
        *)
            san="$san,DNS:$name"
            ;;
    esac
done

openssl ecparam -name prime256v1 -genkey -noout -out "$directory/ca.key"
openssl req -x509 -new -key "$directory/ca.key" -sha256 -days 3650 \
    -subj "/CN=Kotinaytto local CA" \
    -out "$directory/ca.crt"

openssl ecparam -name prime256v1 -genkey -noout -out "$directory/server.key"
openssl req -new -key "$directory/server.key" -subj "/CN=$1" -out "$directory/server.csr"
cat > "$directory/server.ext" <<EOF
basicConstraints=CA:FALSE
keyUsage=digitalSignature
extendedKeyUsage=serverAuth
subjectAltName=$san
EOF
openssl x509 -req -in "$directory/server.csr" -CA "$directory/ca.crt" -CAkey "$directory/ca.key" \
    -CAcreateserial -out "$directory/server.crt" -days 397 -sha256 -extfile "$directory/server.ext"
rm -f "$directory/server.csr" "$directory/server.ext" "$directory/ca.srl"
chmod 644 "$directory/server.crt" "$directory/ca.crt" "$directory/server.key" "$directory/ca.key"
