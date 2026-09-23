# Toteutuksen tila

Päivitetty 23.09.2026.

## Käytössä

- Laravel 13.33.0, PHP 8.5-FPM, PostgreSQL 18.6, nginx. Havaittu asennuspäivänä.
- Docker Compose: `db`, `migrate`, `app`, `worker`, `scheduler`, `nginx`.
- Nginx kuuntelee kotiverkossa porttia 8095 TLS:llä. Tietokannan porttia ei julkaista.
- Sähkö: api.spot-hinta.fi, 15 minuutin hinnat, veroton ja verollinen erikseen.
- Sää: Ilmatieteen laitoksen avoin data, oletuspaikka Lahti, lisenssi CC BY 4.0.
- Kalenteri: Google Calendar API -avain, vain luku. Kalenterin on oltava julkinen. Tunnus syötetään hallinnassa.
- Suomenkielinen kirjautuminen, ensimmäinen ylläpitäjä ympäristömuuttujista, perheenjäsenet, kotityöt, kuittaus ja kuittauksen peruminen.
- Näyttö `/display` ja kertakoodiparitus. Katselulaitteen kuittausoikeus on oletuksena pois.

## Ei vielä tehty

- Julkisesti luotettu varmenne. Selain varoittaa, kunnes paikallinen CA on luotettu.
- Google-kalenterin julkinen kalenteritunnus. Rajapinta-avain on ympäristössä, kalenteria ei ole vielä lisätty hallinnassa.
- Cloudflare Tunnelia ei ole kytketty.
- Raspberry Pi -kioskia ja tablettitestiä ei ole ajettu.
- Varmuuskopion palautuskoetta ei ole tehty.

## Testit

`docker compose exec app php vendor/bin/phpunit` sovelluskontissa. `php artisan test` lukee kontin tuotantoympäristön ennen phpunitia, joten testit ajetaan phpunitilla suoraan. Laitetestejä ei ole ajettu.
