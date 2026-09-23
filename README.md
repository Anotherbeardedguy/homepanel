# Kotinäyttö

Kotiverkossa toimiva näyttö ja hallinta. Sovellus on hakemistossa `sovellus`. Hallinta on osoitteessa `/admin` ja seinänäyttö osoitteessa `/display`. Molemmat käyttävät samaa Laravel-sovellusta ja PostgreSQL-tietokantaa.

Käyttöliittymä on suomeksi. Aikavyöhyke on Europe/Helsinki.

## Vaatimukset

- Docker ja Docker Compose
- Selain tai näyttölaite, johon paikallisen varmenteen voi luottaa

Kontit käyttävät käyttäjätunnusta ja ryhmää `1000`. Jos oman käyttäjän tunnus on muu, vaihda `UID`- ja `GID`-argumentit sekä `compose.yaml`-tiedoston `user`-rivit samaan arvoon.

## Käyttöönotto

Komennot ajetaan hakemistossa `sovellus`.

1. Kopioi ympäristötiedosto:

   ```bash
   cp .env.example .env
   ```

2. Täytä `.env`. Jätä mallin salaisuudet tyhjiksi tietovarastossa ja aseta omat arvot vain paikalliseen tiedostoon:

   - `APP_URL` on osoite, jolla selain avaa palvelun, mukaan lukien `https://` ja portti.
   - `DB_PASSWORD` on PostgreSQL-salasana.
   - `HOMEPANEL_ADMIN_USERNAME` ja `HOMEPANEL_ADMIN_PASSWORD` luovat ensimmäisen ylläpitäjän, jos ylläpitäjää ei vielä ole.
   - `GOOGLE_CALENDAR_API_KEY` tarvitaan vain, jos kalenteri luetaan Googlen julkisella rajapinnalla.

3. Luo sovellusavain. Komento kirjoittaa `APP_KEY`-arvon `.env`-tiedostoon:

   ```bash
   docker compose run --rm --no-deps --user 1000:1000 app php artisan key:generate
   ```

4. Luo paikallinen varmenne sille nimelle tai IP-osoitteelle, jota selain käyttää. `192.0.2.10` on vain esimerkki, ei oman verkon osoite:

   ```bash
   sh docker/nginx/issue-local-cert.sh 192.0.2.10
   ```

   Lisänimet voi antaa seuraavina argumentteina. Jos varmenne on jo luotu, komento ei uusi sitä. Uutta osoitetta varten poista hakemiston `docker/nginx/certs/` sisältö ja aja komento uudelleen.

5. Luota tiedostoon `docker/nginx/certs/ca.crt` jokaisessa selaimessa ja puhelimessa, joka avaa palvelun. Istuntoeväste on merkitty suojatuksi, joten selain tarvitsee luotetun TLS-yhteyden.

6. Käynnistä:

   ```bash
   docker compose up -d --build
   ```

7. Avaa `APP_URL` ja kirjaudu osoitteessa `/login`.

Nginx julkaisee TLS:n isännän portissa `8095`. Portin voi vaihtaa `compose.yaml`-tiedostossa. PostgreSQL-porttia ei julkaista verkkoon. Jos vaihdat portin tai nimen, päivitä myös `APP_URL` ja varmenne.

## Näyttölaite

Avaa näyttölaitteella `/display/pair`. Hyväksy laite hallinnan laitesivulla. Näyttösessio on erillinen hallinnan kirjautumisesta. Kuittausoikeus on laitekohtainen ja oletuksena pois päältä.

## Myöhemmät ylläpitäjät

```bash
docker compose exec app php artisan homepanel:create-admin tunnus
```

Komento kysyy salasanan kahdesti eikä tulosta sitä.

## Tietolähteet

- Sähköhinnat haetaan palvelusta `api.spot-hinta.fi`.
- Sää haetaan Ilmatieteen laitoksen avoimesta datasta (CC BY 4.0). Paikka vaihdetaan hallinnasta.
- Google-kalenteri luetaan rajapinta-avaimella. Kalenterin on oltava julkinen, ja sen tunnus syötetään hallintaan. Salainen iCal-osoite on vaihtoehto; osoite jää palvelimelle eikä näy näytöllä.

Synkronoinnit ajetaan `scheduler`- ja `worker`-konteissa.

## Tarkistukset

```bash
docker compose exec app php vendor/bin/phpunit
docker compose config --quiet
```

## Julkinen tietovarasto

Älä commitoi tiedostoa `.env` äläkä hakemistoa `docker/nginx/certs/`. Niissä ovat sovellusavain, salasanat ja varmenteen yksityinen avain. Hakemistoon `public/downloads/` voi laittaa paikallisen kioskisovelluksen asennuspaketin; paketteja ei tallenneta tietovarastoon. Nämä on jätetty pois `.gitignore`-tiedostossa.
