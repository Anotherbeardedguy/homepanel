# Toteutus Cursorissa: Laravel-palvelin ja hallinta

Päivitetty 22.09.2026. Käytä tätä ohjetta ensin ja sen jälkeen kotinäytön vastaavaa ohjetta samassa projektissa. Ohje ei asenna tai käynnistä sovellusta.

## Malli ja projektin avaaminen

Tarkistushetkellä Cursorin virallinen malliluettelo sisältää Grok 4.7:n uusimpana Grok-versiona. Valitse se Cursorin Agent-näkymän mallivalitsimesta, jos se on käytettävissä tilauksellasi. Valitse malli itse; prompti tai sääntötiedosto ei vaihda mallia. Myöhemmällä toteutuskerralla tarkista uusin saatavilla oleva vakaa Grok-versio uudelleen. [Cursorin malliluettelo](https://cursor.com/docs/models-and-pricing) · [Grok 4.7](https://cursor.com/docs/models/grok-4-7)

Avaa Cursorissa yhteinen `Home stuff` -kansio, jotta molempien suunnittelukansioiden tiedot ovat luettavissa. Liitä aloitusviestiin Cursorin tiedostoviittauksilla tämä ohje, SPEKSI.md, PAKETIT-JA-LAHTEET.md sekä kotinaytto/SUUNNITELMA.md, DESIGN.md ja LAITTEET-JA-ARKKITEHTUURI.md. Markdown-ohje ei automaattisesti toimi Cursor-sääntönä. Halutessasi pyydä agenttia luomaan tästä tiivis `.cursor/rules/home-screen.mdc`, `alwaysApply: true`, yhteiseen projektijuureen; säilytä pitkät speksit erillisinä. [Cursor Rules](https://cursor.com/docs/rules)

Sovelluksen oletuspaikka on uusi `sovellus/` yhteisen projektijuuren alla. Tarkista ensin olemassa olevat tiedostot: jos Laravel jo löytyy, jatka sitä. Älä luo erillisiä Laravel-projekteja suunnittelukansioihin tai alusta uutta sisäkkäistä Git-repositoriota.

## Kopioitava aloitusprompti

```text
Toteuta tämän projektin kodin tietonäytön Laravel-palvelin ja tavallinen
suomenkielinen hallintapaneeli. Lue ensin liitetyt suunnitelmat ja kaikki
soveltuvat AGENTS.md- ja Cursor-säännöt. Tutki Git-tila, hakemistorakenne,
olemassa oleva sovellus, lukitustiedostot ja testityökalut ennen muutoksia.

Arkkitehtuuri: yksi Laravel-sovellus, PostgreSQL, Docker Compose,
/admin Blade-hallinnalle ja /display samassa sovelluksessa olevalle
kotinäytölle. Näytön päivitys käyttää saman originin suojattua JSON-reittiä.
Ei erillistä API-palvelua, Next.js:ää, Vuea tai Reactia. Palvelin pyörii
kotiverkon Docker-hostissa; näyttö on Raspberry Pi:n tai soveltuvan tabletin
selain. Galaxy Tab A SM-T555 on vasta yhteensopivuustestattava vaihtoehto.

Pidä kotinaytto/ ja hallintapaneeli/ suunnittelukansioina. Jos toteutusta
ei vielä ole, rakenna sovellus yhteisen juuren sovellus/-kansioon.
Tarkista uusimmat vakaat, keskenään yhteensopivat ohjelmistoversiot
virallisista lähteistä ja kirjaa lähde sekä päivämäärä. Älä pidä
suunnittelumuistion versionumeroita ikuisesti uusimpina. Älä asenna
tuntemattomia API-kääreitä tai valmista dashboard-repositoriota.

Lahti ja Google Calendar ovat alkuasetukset. Hallinnasta muutetaan
paikka, kalenterit, sähkön hintarajat ja tekstit, kotityöt, näytön asettelu
ja kaikki sisältöasetukset. Toteuta ensin pienin läpi toimiva kokonaisuus:
kirjautuminen, kotityön luonti, suojattu näyttö ja kotityön kuittaus.
Jatka sen jälkeen alla olevassa toteutusjärjestyksessä.

Älä korvaa puuttuvia API-tunnuksia tuotannossa keksityllä datalla.
Testidata on erillinen, selvästi merkitty kehitysympäristön toiminto.
Jos ulkoinen tunnus puuttuu, toteuta adapteri, testit ja hallinnan
puuttuvan yhteyden tila; jatka muita työpaketteja. Pyydä tunnusta vasta
aidon yhteyden testausta varten, älä pyydä salaista arvoa keskusteluun.

Tee pieniä muutoksia, testaa ne ja pidä toteutuksen README sekä
TOTEUTUKSEN-TILA.md ajan tasalla. Raportoi toteutetut asiat, ajetut testit,
tiedostot ja vielä todentamattomat integraatiot. Älä julkaise palvelua
internetiin tai muuta kotiverkon asetuksia ilman erillistä pyyntöä.
```

## Toteutusjärjestys ja laadun portit

1. **Perusta:** varmennetut riippuvuudet, Dockerfile/Compose, `.env.example`, PostgreSQL, migraatiot, ensimmäisen ylläpitäjän komentoriviluonti ja kirjautuminen. Gate: palvelin käynnistyy ohjeen mukaan, tietokanta ei ole LAN-portissa, salaisuudet eivät ole repositoriossa.
2. **Läpikulkeva toiminto:** perheenjäsenet, kotityöt, kuittaus ja näyttöoikeus. Gate: oikeudeton pyyntö estyy, tuplakuittaus on idempotentti ja näyttö saa oikean sisällön.
3. **Asetukset ja laitteet:** profiilit, esikatselu, versionoitu julkaisu, kertakoodiparitus ja peruutus. Gate: katselulaite ei hallitse asetuksia tai lue tunnuksia.
4. **Sähkö:** virallinen tietolähde, yksikkömuunnos, vartit, ALV/marginaali, hintaperuste ja virhetila. Gate: rajat, negatiiviset hinnat, aukot ja kesäaika testattu.
5. **Sää ja kalenteri:** Foreca ja Google vain lukuun. Gate: timeout/429, puuttuva tunnus, sivutus, poistetut tapahtumat, toistumiset ja valtuutuksen vanheneminen käsitelty.
6. **Ylläpito:** työjonot, ajastin, lokit, säilytysajat, varmuuskopio ja palautus. Gate: palautus todistettu erillisessä testitietokannassa, ei vain varmuuskopiotiedoston olemassaolo.
7. **Luovutus kotinäyttötyölle:** dokumentoitu näyttövastauksen sopimus, testifixturet, käynnistyskomennot ja käyttöoikeudet. Jatka kotinaytto/CURSOR-GROK.md:n mukaan samassa sovelluksessa.

## Täsmennykset toteuttavalle agentille

- Laravelin perushallinta tarkoittaa Blade-lomakkeita/listoja ja Laravelin autentikointia; Laravel-paketti ei yksin sisällä admin-tuotetta. Älä lisää Filamentia tai muita hallintakehyksiä ilman todettua tarvetta.
- Googlen OAuthissa suosi Googlen virallista ylläpidettyä PHP-clientiä/valtuutuskirjastoa, jos yhteensopivuus ja uusin vakaa julkaisu on varmennettu. Oma token-protokollatoteutus ei ole riippuvuuksien vähentämisen tavoite. Dokumentoi hyväksytty paketti ja lukitse transitiiviset riippuvuudet.
- Näyttörajapinta on sisäinen osa samaa Laravel-sovellusta. Evästeistunto, laiteoikeus ja CSRF on kytkettävä myös `/api/v1`-reitteihin eksplisiittisesti; reitin nimi ei ota niitä automaattisesti käyttöön.
- Säilytä aiemmissa spekseissä olevat hintojen voimassaolo- ja yksityisyysrajat. Lisää sopimukseen API-skeeman versio, palvelinaika ja per-widget-tila.
- Kaikki asiakastekstit suomeksi, koodi ja luokkien nimet englanniksi. Käytä fi-FI-esitystä ja Europe/Helsinki-paikallisaikaa; tallennus UTC.
- Käytä olemassa olevan repositorion paketinhallintaa. Jos frontend-build ei ole tarpeen, älä lisää npm-riippuvuuksia vain tottumuksesta. Jos Laravelin työkaluketju tarvitsee buildin, Node voi olla rakennusvaiheessa ilman tuotannon Node-palvelinta.

Tarkistukset sovelluskansiosta: `composer validate --strict`, `composer audit`, `php artisan test` sekä `docker compose config --quiet`. Käytä lisäksi valitun projektin lintteriä ja mahdollista asset-buildia. Älä suorita puuttuvaa komentoa tai väitä sitä läpäistyksi. Laitetestit ja todelliset API-savukokeet erotellaan paikallisista fixturetesteistä.

