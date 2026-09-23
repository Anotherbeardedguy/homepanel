# Kotinäytön Laravel-hallintapaneeli

Määrittely 22.09.2026. Kaikki käyttäjän näkemä teksti on suomea. Lahti ja Google Calendar ovat vahvistetut alkuasetukset, joita voi muuttaa hallinnassa. Toteutusta tai palvelinasennusta ei ole vielä tehty.

## Arkkitehtuuri ja rajaus

Toteutus Cursorissa: [CURSOR-GROK.md](CURSOR-GROK.md). Next.js-vaihtoehdon arvio, saman palvelimen sisäisen rajapinnan merkitys ja Samsung SM-T555 -näyttövaihtoehto on kuvattu [arkkitehtuuritarkennuksessa](../kotinaytto/LAITTEET-JA-ARKKITEHTUURI.md). Näyttölaite tarvitsee selaimen; omaa kioskisovellusta ei rakenneta.

Käyttäjän tarkennus 22.09.2026: hallinta saa olla tavallinen, yksinkertainen Laravel-hallinta. Toteutetaan palvelimella renderöidyt Blade-listat ja lomakkeet; Vuea, Nextiä tai muuta SPA-kehystä ei käytetä. Laravel ei yksin sisällä valmista admin-paneelia: tarvittavat perusnäkymät tehdään sen omilla ominaisuuksilla. Hallinnan ulkoasu on hillitty, ja kotinäytön erillinen visuaalinen suunnittelu on [DESIGN.md](../kotinaytto/DESIGN.md)-tiedostossa.

Yksi Laravel 13 -sovellus tarjoaa mobiiliystävällisen hallinnan, Raspberry Pi:n kotinäytön ja paikallisen JSON-rajapinnan. Käyttöliittymä tehdään Bladella, omalla CSS:llä ja pienellä määrällä selaimen JavaScriptiä. Erillistä admin-kehystä, Reactia, Node-palvelinta tai epävirallisia sää-/sähköpaketteja ei tarvita. PHP 8.5, PostgreSQL 18, viralliset konttikuvat ja Docker Compose. Tarkat varmennetut lähtöversiot: [pakettimuistio](PAKETIT-JA-LAHTEET.md).

```text
Foreca / ENTSO-E / Google Calendar
                  │ HTTPS, palvelinpuolen tunnukset
          Laravel-taustatyöt ── PostgreSQL
                  │
          Laravel + HTTPS-välityspalvelin
                  │ kotiverkko
         ┌────────┴─────────┐
    Pi /display       puhelin /admin
```

Sovellus toimii kotiverkossa ilman julkista porttiohjausta. Ulkoisten tietojen päivitys tarvitsee internetin; paikalliset askareet ja hallinta eivät. Ensiversio palvelee yhtä kotitaloutta. Kodin omat kalenterimerkinnät voidaan luoda paikallisesti; Googlen tapahtumia vain luetaan. Julkinen SaaS, maksumuuri, älykodin laiteohjaus ja GPIO-liikennevalo eivät kuulu ensimmäiseen versioon.

## Hallinnan näkymät ja kaikki säädettävät asetukset

| Näkymä | Toiminnot |
|---|---|
| Yhteenveto | Kotinäytön esikatselu, jokaisen integraation tila, viimeinen onnistuminen, seuraava yritys ja virheen korjausohje |
| Näyttö ja asettelu | Korttien näkyvyys, järjestys, koko, otsikko, vaaka-/pystysuunta, teema, tekstikoko, listojen rivimäärä, hiljaiset ajat ja laitekohtainen profiili |
| Sähkö | Tietolähteen tunnukset, hintaperuste, ALV, veroton marginaali, vihreä/punainen raja, jokaisen tilan ohjeteksti ja päivitysväli sallituissa rajoissa |
| Sää | Paikkahaku (aluksi Lahti), tallennetun paikan tunnus/koordinaatit, näytettävät kentät, Foreca-tunnus, päivitysväli ja vanhentumisrajat |
| Kalenterit | Google-yhteyden muodostus/katkaisu, kalenterivalinnat, näyttönimet, yksityisyys, paikan näkyvyys, järjestys, synkronointiväli ja paikalliset tapahtumat |
| Kotityöt | Lisää, muokkaa, arkistoi, osoita henkilölle, aseta määräpäivä/toistuminen, kuittaa tehdyksi ja peru kuittaus |
| Perhe | Näyttönimet ja tunnistevärit, vapaaehtoiset kirjautumistunnukset; lapsen nimeä varten ei tarvitse sähköpostia |
| Laitteet | Paritus, nimi, viimeksi nähty, profiili, kuittausoikeus ja laiteistunnon peruutus |
| Ylläpito | Käyttäjät, säilytysajat, varmuuskopion tila, integraatioiden testaus ja turvallinen asetusten vienti ilman salaisuuksia |

Kaikki kotinäytön sisältöasetukset ovat hallinnassa. Palvelimen salausavainta, tietokantasalasanaa, konttiversioita tai käyttöjärjestelmän päivityksiä ei muuteta selainlomakkeilla. AccuWeather ja muut kalenteripalvelut voidaan lisätä palveluntarjoaja-adaptereina myöhemmin; hallinnassa ei näytetä toimimatonta valintaa.

Muokkaus sisältää suomenkieliset kenttäohjeet, palvelinvalidoinnin, esikatselun ja Tallenna-painikkeen. Virheellinen tallennus säilyttää syötteen. Tallennuksen jälkeen vahvistus ”Asetukset tallennettu”. Julkaistu asetusversio vaihtuu transaktiossa; virhe ei jätä puoliksi päivitettyä näyttöä. Integraation tunnus näkyy vain peitettynä, uusi arvo syötetään erilliseen vaihtokenttään.

## Käyttäjät ja laitteiden oikeudet

- Ylläpitäjä: kaikki asetukset, käyttäjät, integraatiot ja laitteet.
- Perheenjäsen: kotinäyttö, kotityölista ja suoritusten kuittaus. Ei tunnuksia tai asetuksia.
- Näyttölaite: vain sille julkaistu tietosisältö. Kuittaus erillisenä, oletuksena pois päältä olevana oikeutena.

Laravelin omat istunnot, salasanahajautus, CSRF-suojaus ja resurssikohtaiset policy-tarkistukset. Ei avointa rekisteröitymistä. Ensimmäinen ylläpitäjä luodaan kertaluonteisella artisan-komennolla; salasana kysytään piilotettuna. Lukittu ylläpitäjä palautetaan palvelimella hallitulla komennolla, ei kovakoodatulla takaovella.

Paritus: Pi pyytää satunnaisen kertakoodin, ylläpitäjä hyväksyy laitteen, selain saa Secure/HttpOnly/SameSite-istuntoevästeen. Koodi vanhenee 5 minuutissa, yrityksiä rajoitetaan ja hyväksyntä on atominen. Palvelin säilyttää vain tunnisteen tiivisteen. Laitteen peruutus estää seuraavat pyynnöt ja tyhjentää näkyvän sisällön seuraavassa tarkistuksessa. Offline-laitteen välitöntä tyhjennystä ei voida luvata.

## Tietomalli

Kaikissa muuttuvissa tauluissa tunniste sekä luonti- ja muokkausaika. Ajankohdat UTC:ssa; paikalliset kalenteripäivät DATE-tyyppinä. Sovelluksen yksi kotitalous on eksplisiittinen rajaus, ei näennäinen moniasiakastuki.

| Taulu | Keskeiset kentät ja säännöt |
|---|---|
| users | nimi, kirjautumistunnus, password_hash, role; uniikki kirjautumistunnus |
| family_members | näyttönimi, väri, optional user_id, aktiivisuus |
| display_profiles | nimi, versionumero, validoidut kortti- ja näyttöasetukset |
| devices | nimi, profile_id, token_hash, oikeudet, last_seen_at, revoked_at |
| integrations | provider, salatut tunnukset, tila, last_success_at, last_error_code |
| electricity_settings | rajat, hintaperuste, veroton marginaali, ALV ja viestit |
| electricity_prices | provider, area, start_at, end_at, price_eur_mwh DECIMAL(18,6), fetched_at; uniikki provider+area+start_at |
| weather_snapshots | sijainti, havainto-/ennusteaika, fetched_at, expires_at, normalisoitu sisältö |
| calendar_sources | integration_id, external_calendar_id, näyttönimi ja yksityisyysasetukset |
| calendar_events | source_id, external_event_id, esiintymän tunniste, ajat tai päivämäärät, otsikko, paikka, näkyvyys; uniikki lähde+esiintymä |
| local_events | otsikko, ajat tai koko päivän päivämäärät, näkyvyys; aluksi vain kertatapahtumat |
| chores | otsikko, kuvaus, assignee_id, toistumistapa, valitut viikonpäivät, eräaika, aktiivisuus |
| chore_occurrences | chore_id, due_date, due_at, status, completed_at/by; uniikki chore_id+due_date |
| audit_events | tekijän/laiteen tunniste, toiminto, kohteen tunniste, aika; ei salaisuuksia |

Tietokantarajoitteet estävät negatiiviset aikavälit ja päällekkäiset suoritukset; negatiivinen sähkön hinta on sallittu. Hakemistot hintojen aikaväleille, tapahtumien ajoille ja askareiden päivälle/tilalle. Toistuvien askareiden esiintymät luodaan ennakkoon idempotentisti. Mallin muutos koskee tulevia avoimia esiintymiä, ei historiaa; menneet tekemättömät säilyvät, kunnes ne kuitataan tai arkistoidaan. Koko päivän tapahtuman loppupäivä on poissulkeva, myös Google-tuonnissa.

## Integraatioiden sopimukset ja ajastus

Kaikki ulkoiset HTTP-kutsut Laravelin HTTP-asiakkaalla; ENTSO-E XML luetaan PHP:n XML-tuella ulkoiset entiteetit ja verkkolataus estettyinä. Palveluntarjoajan sovitin palauttaa normalisoidut arvot ja voimassaoloajat. Rajoitukset: yhteys 5 s, kokonaispyyntö 15 s, rajattu vastauskoko, enintään kolme uudelleenyritystä viiveellä. 429 noudattaa Retry-After-ohjetta, 401/403 ei käynnistä loputonta yrityssilmukkaa. Työn timeout on lyhyempi kuin jonon retry_after; samalle integraatiolle ei ajeta päällekkäisiä hakuja.

Google-kalenteri haetaan rajapinta-avaimella (`GOOGLE_CALENDAR_API_KEY`). Avain kelpaa julkiselle kalenterille. Kalenteritunnus syötetään hallinnassa ja tapahtumat luetaan Events.list-kutsulla. Hallintaan tallennettu avain salataan; ympäristömuuttuja toimii varalla. Irrotus poistaa tallennetun avaimen ja tuodut tapahtumat. [Events.list](https://developers.google.com/workspace/calendar/api/v3/reference/events/list)

Kalenteri synkronoidaan rajatulta aikaväliltä (eilen–seuraavat 7 päivää) täydellisenä sivutettuna joukkona. Google laajentaa toistuvat tapahtumat (`singleEvents=true`). Vasta kaikkien sivujen onnistuttua korvataan kyseisen ikkunan tapahtumat transaktiossa; osittainen haku ei poista vanhoja tapahtumia. Välimuistin päivä vaihtuu Suomen ajassa. Kalenterin poiskytkentä poistaa sen tuodun sisällön ja katkaisu poistaa valtuutustiedot.

Ajastus: sähkö 60 min, sää 30 min, kalenteri 5 min, askareiden esiintymät päivittäin ja mallia muutettaessa, säilytyssiivous päivittäin. Päivitä nyt noudattaa samaa kiintiötä ja lukkoa. Sähköhintojen ja korttien virhe-/vanhentumissäännöt ovat [kotinäytön suunnitelmassa](../kotinaytto/SUUNNITELMA.md).

## Paikallinen rajapinta

| Reitti | Oikeus ja käyttäytyminen |
|---|---|
| GET /api/v1/display | Laiteistunto; julkaistu profiili ja neljän kortin normalisoitu sisältö |
| POST /api/v1/chores/{occurrence}/complete | Perheenjäsen tai erikseen sallittu laite; atominen ja idempotentti |
| POST /api/v1/chores/{occurrence}/reopen | Perheenjäsen; auditoitu kuittauksen peruutus |
| POST /api/v1/device-pairings | Rajoitettu paritusaloitus, ei vielä pääsyä sisältöön |
| POST /admin/device-pairings/{id}/approve | Ylläpitäjä + CSRF |
| GET /health/live | Prosessin elossaolo ilman sisäisiä tietoja |
| GET /health/ready | Sisäverkon tarkistus: tietokanta ja migraatiot |

Näyttövastauksessa `schemaVersion`, `settingsVersion`, `serverTime`, `timezone` ja `widgets`. Kortilla `status` (loading/ok/empty/stale/error/disabled), `fetchedAt`, `validUntil`, `data` ja suomenkielinen `message`. Sähkön hinnat lähetetään desimaalimerkkijonoina, valuutta ja yksikkö eksplisiittisinä. Sähkökortti sisältää nykyisen ja saatavilla olevat seuraavat jaksot sekä luokittelurajat. Ei integraatioiden tunnuksia tai raakoja API-vastauksia.

Vastausmuoto: `{ "success": true, "data": {}, "error": null }`. Virheessä `success=false`, `data=null` ja `error={code,message}`. Käytä 401/403/422/429/503 tilanteen mukaan. Yhden kortin lähdevirhe ei kaada koko näyttövastauksen onnistumista. Tietokantaoperaatio estää rinnakkaisten kuittausten kaksoiskirjauksen.

## Docker ja kotiverkon ylläpito

Compose-palvelut: HTTPS-välityspalvelin, `app` (PHP-FPM), `worker`, `scheduler` ja `db`. App/worker/scheduler käyttävät samaa muuttumatonta sovelluskuvaa. Laravelin tietokantajono ja tietokantavälimuisti riittävät tähän kuormaan; Redis ei kuulu aloitukseen. Vain välityspalvelimen HTTPS-portti julkaistaan valittuun LAN-osoitteeseen. Tietokannan ja PHP-FPM:n portit jäävät konttiverkkoon. Kontit eivät käytä privileged-tilaa tai Docker-socketia.

Paikallinen DNS, esimerkiksi `koti.home.arpa`, ja luotettu paikallinen TLS-varmenne asennetaan Pi:lle ja hallintalaitteille. OAuth voi edellyttää edellä kuvattua erillistä omistettua nimeä. Sertifikaattivaroitusten ohittaminen ei ole käyttöönottoratkaisu. Kotiverkon osoite ei yksin korvaa kirjautumista.

Pysyvät volyymit: PostgreSQL ja tarvittava Laravel storage. Rakennusvaihe asentaa lukitut Composer-riippuvuudet ilman kehityspaketteja, suorittaa tarkistukset ja muodostaa kuvan. Tuotannossa `APP_DEBUG=false`, salaisuudet ympäristöstä tai suojatusta secret-tiedostosta, ei Gitissä. Migraatiot erillisellä kertakomennolla ennen uuden version käyttöönottoa. ARM64- ja AMD64-kuvien tuki varmistetaan palvelinlaitteen mukaan.

Dokumentoitavat `.env.example`-kentät: APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL, APP_LOCALE=fi, APP_FALLBACK_LOCALE=fi, DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, SESSION_DRIVER=database, CACHE_STORE=database, QUEUE_CONNECTION=database, GOOGLE_CALENDAR_API_KEY. APP_KEY ja salasanat jäävät mallissa tyhjiksi. Esimerkin toiminta-alueaikavyöhyke Europe/Helsinki on sovellusasetus; tallennusaika UTC. Foreca-/ENTSO-E-tunnukset syötetään hallinnassa ja salataan APP_KEY:llä.

Varmuuskopio päivittäin: tietokanta, konfiguraatio ja erikseen suojattu APP_KEY, jota ilman integraatiosalaisuuksia ei voida palauttaa. Kopio salattuna eri fyysiselle laitteelle; oletussäilytys 7 päivä- ja 4 viikkokopiota. Kuukausittainen palautuskoe tyhjään ympäristöön. Tavoite: enintään 24 h tietohävikki ja palautus 2 h:ssa. Päivitys: varmuuskopio, tarkistukset, migraatio, uusi kuva, terveystarkistus ja näyttökoe. Palautus edelliseen kuvaan vain yhteensopivalla skeemalla; muuten palautetaan testattu varmuuskopio huoltotilassa.

## Yksityisyys ja havaittavuus

Ei analytiikkaa, mainoksia tai ulkoisia fontteja. Minimoidaan Googlelta tallennetut tiedot. Oletussäilytys: menneet kalenteritapahtumat 7 vrk, hintahistoria 90 vrk, kotitöiden historia 90 vrk, auditointi 30 vrk. Hallinta muuttaa ajat ja voi poistaa perheenjäsenen tiedot. Poisto varmuuskopioista tapahtuu säilytysajan päättyessä, mikä kerrotaan hallinnassa.

Rakenteiset lokit stdoutiin: request_id, integraatio, kesto, tuloskoodi; ei tokeneita, tapahtumien otsikoita tai henkilöiden nimiä. Hallinta näyttää workerin/schedulerin sykkeen ja viimeisen onnistuneen synkronoinnin, jotta elossa oleva HTTP-palvelin ei peitä pysähtynyttä taustatyötä. Ulospäin tehtävät kutsut rajataan toteutettujen palveluntarjoajien osoitteisiin; käyttäjä ei anna vapaata URL-osoitetta palvelimen haettavaksi.

## Työpaketit ja valmis-kriteerit

1. Integraatioiden oikeudet, versiolukitus, Docker-runko, tietokanta ja kirjautuminen.
2. Hallinnan asetukset, julkaistu näyttöprofiili, laiteparitus ja suomenkielinen esikatselu.
3. Sähkö, sää, Google ja askareet; häiriötilat ja säilytyssiivous.
4. Pi-kioski, oikea perhetesti, varmuuskopiointi ja käyttöohjeet.

Testit: hintayksikkö ja ALV, negatiivinen hinta, täsmälliset rajat, 92/96/100 vartin päivät, puuttuva jakso, yön yli/koko päivän/toistuva/peruttu tapahtuma, kalenterisivutuksen epäonnistuminen, 401/429/timeout, oikeusmatriisi, CSRF, parituskoodin vanheneminen ja uudelleenkäyttö, samanaikainen tehtäväkuittaus ja ajastimen uudelleenajo. Integraatiotesteissä käytetään anonymisoituja vastauksia ja lisäksi suoritetaan oikeiden tunnusten savutesti ilman salaisuuksien lokitusta.

Käyttöliittymäkoe mobiilissa, näppäimistöllä ja oikealla Pi:llä. Vaihda Lahti toiseen paikkaan, valitse eri Google-kalenteri, muuta hintarajaa ja ohjetekstiä, piilota kortti sekä lisää ja kuittaa tehtävä. Kaiken tulee näkyä näytössä 60 sekunnissa viimeistään onnistuneen tietohaun jälkeen; hitaan ulkoisen haun odotus esitetään tilana. Säilytä korttien riippumattomuus katkaisemalla yksi tietolähde kerrallaan.

Julkaisun tarkistukset: sovellustestit, Composer-riippuvuusauditointi, konttikuvan haavoittuvuustarkistus, Compose-konfiguraation validointi, migraatio- ja palautuskoe sekä 48 h näyttökoe. Tarkat komennot kirjataan toteutuksen README:hen olemassa olevan työkaluketjun mukaan. Nyt tehdyt dokumentit eivät väitä näitä testejä suoritetuiksi.
