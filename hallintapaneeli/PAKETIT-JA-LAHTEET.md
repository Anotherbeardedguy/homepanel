# Varmennetut teknologiat ja lähteet

Tarkistettu 22.09.2026 virallisista lähteistä. Suunnitteluvaiheessa ei ole asennettu paketteja. Versiot ovat tarkistushetken havaintoja, eivät lupaus uusimmasta versiosta myöhemmällä toteutuspäivällä.

| Osa | Tarkistettu lähtökohta | Virallinen lähde |
|---|---|---|
| Laravel | 13.32.0, julkaistu 15.09.2026; uusin julkaisulistassa | [laravel/framework releases](https://github.com/laravel/framework/releases) |
| PHP | 8.5.10, vakaa; PHP 8.6 on vielä ennakkoversio | [PHP](https://www.php.net/) |
| Yhteensopivuus | Laravel 13 tukee PHP 8.3–8.5 | [Laravel-tuki](https://laravel.com/framework/docs/releases) |
| PostgreSQL | Virallinen tukitaulukko näyttää 18.6 version uusimmassa 18-sarjassa | [Versioning policy](https://www.postgresql.org/support/versioning/) |
| Raspberry Pi OS | 64-bit Desktop, Debian 13 Trixie; lataussivulla 15.09.2026 julkaisu | [Raspberry Pi OS](https://www.raspberrypi.com/software/operating-systems/) |
| Chromium | Raspberry Pi OS:n virallisen pakettivaraston vakaa päivitys; tarkkaa pakettiversiota ei tarkistettu | [OS-ohje](https://www.raspberrypi.com/documentation/computers/os.html) |
| Docker Engine / Compose | Virallinen vakaa jakelu; Engine 29 -julkaisusarja tarkistettu, tarkka asennettava versio lukitaan toteutushetkellä | [Engine julkaisut](https://docs.docker.com/engine/release-notes/29/) · [Compose](https://docs.docker.com/compose/intro/compose-application-model/) |

PHP-, PostgreSQL-, Composer- ja HTTPS-välityspalvelinkuvien tarkat tagit ja digestit sekä Composerin versio varmistetaan ennen Docker-toteutusta. Välityspalvelin valitaan virallisesta ylläpidetystä tuotteesta (esimerkiksi NGINX); sen tarkkaa versiota ei ole tässä hyväksytty. Sama koskee kehityksen testityökaluja. Tätä dokumenttia ei tule tulkita kaikkien transitiivisten riippuvuuksien valmiiksi auditoinniksi.

## Pakettien hyväksymissääntö

Käytetään ensin PHP:n, Laravelin, selaimen ja käyttöjärjestelmän omia ominaisuuksia. Uusi riippuvuus hyväksytään vain dokumentoituun tarpeeseen: virallinen omistaja tai vakiintunut ylläpitäjä, aktiivinen ylläpito, selvä lisenssi, julkinen historia, laaja käyttöpohja ja tietoturvailmoitusten käsittely. Tähtimäärä tai yksittäinen arvostelu ei yksin osoita turvallisuutta. Pieniä tuntemattomia API-kääreitä, valmiita dashboard-repoja ja hylättyjä lisäosia ei käytetä.

Toteutushetkellä tarkistetaan virallisesta julkaisulähteestä uusin vakaa ja kokonaisuuden kanssa yhteensopiva versio. Composer ratkaisee transitiiviset riippuvuudet ja `composer.lock` tallennetaan Gitiin. Kontit lukitaan versioon ja digestiin; tuotantoon ei käytetä liikkuvaa `latest`-tagia tai automaattista valvomatonta päivitystä. Paketit asennetaan testatussa rakennuksessa, tarkistetaan auditoinnilla ja kirjataan versioinventaarioon. Uudelleenrakennus käyttää samoja lukituksia.

Tässä ratkaisussa ei tarvita erillisiä kolmannen osapuolen PHP-paketteja säälle, sähkölle, Google-kalenterille tai hallintapaneelille. OAuth- ja HTTP-virrat toteutetaan virallisten dokumenttien ja Laravelin perusominaisuuksien avulla; tarvittava virallinen SDK arvioidaan erikseen, jos se vähentää toteutusriskiä.

## Tietopalvelut

| Palvelu | Varmennettu ja avoimeksi jäävä asia | Lähde |
|---|---|---|
| ENTSO-E | Virallinen tietolähde ja tokenin hankintamenettely. Suomen varttivastaus ja käytön ehdot hyväksytään aidolla tunnuksella ennen julkaisua. | [Transparency Platform](https://www.entsoe.eu/data/transparency-platform/) · [API-tunnus](https://transparencyplatform.zendesk.com/hc/en-us/articles/12845911031188-How-to-get-security-token) |
| Nord Pool | Varttimarkkinan aloitus vahvistettu; sopimuspohjainen vaihtoehtoinen hintalähde, ei oletettua ilmaista API:a. | [15 minuutin tiedote](https://www.nordpoolgroup.com/en/message-center-container/newsroom/exchange-message-list/2025/q3/market-coupling-steering-committee-confirms-go-live-of--15-minute-mtu-in-sdac-on-trading-day-30-september-2025-for-delivery-day-1-october-2025/) |
| Foreca | Virallinen API, suomi tuettuna, API-avain ja tilaussuunnitelma. Hinta, kiintiö, ikonilisenssi ja välimuistin sallittu käyttö tarkistetaan valitusta tilauksesta. | [Dokumentaatio](https://developer.foreca.com/) · [Tuote](https://business.foreca.com/weather-api) |
| Google Calendar | Virallinen Events.list tukee tapahtumahakua julkisesta kalenterista rajapinta-avaimella. Yksityinen kalenteri ei ole luettavissa pelkällä avaimella. | [Events.list](https://developers.google.com/workspace/calendar/api/v3/reference/events/list) |

AccuWeather ei kuulu valittuun riippuvuuslistaan eikä sen paketteja ole hyväksytty. Foreca on tämän suunnitelman sääpalvelu. Maksullista tilausta, verkkotunnusta tai laitehankintaa ei ole tehty. Ennen toteutusta tarvitaan tieto Pi:n mallista, näytöstä ja Docker-palvelimen laitteistosta sekä API-tilien käyttöoikeudet.
