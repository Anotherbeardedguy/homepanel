# Yksi palvelin, selain näytöllä

Päivitetty 22.09.2026. Tämä täydentää aiempia suunnitelmia ja Cursor-ohjeita.

## Suositeltu rakenne

Kotiverkon Docker-hostissa toimii yksi Laravel-sovellus: `/admin` hallintaan, `/display` kotinäyttöön. Molemmat käyttävät samaa tietokantaa, autentikointia ja julkaistuja asetuksia. Raspberry Pi tai Samsung-tabletti avaa `/display`-osoitteen selaimessa. Näyttölaitteeseen ei tarvitse asentaa PHP:tä, PostgreSQL:ää, Next.js:ää tai sovelluksen lähdekoodia.

```text
Kotiverkon Docker-palvelin
  HTTPS → Laravel → PostgreSQL
            ├─ /admin   ← tietokone / puhelin
            └─ /display ← Pi Chromium / hyväksytty tabletin selain
```

Palvelin voi teknisesti olla myös samalla riittävän tehokkaalla Raspberry Pi:llä kuin näyttö. Erillinen olemassa oleva Docker-host on tässä oletus: sen ylläpito ja näytön vaihto pysyvät riippumattomina. Samsung toimii näyttöpäätteenä, ei Docker-hostina.

## Next.js ja API: mitä samassa hostauksessa oleminen tarkoittaa?

Next.js ja Laravel voivat toimia samalla fyysisellä palvelimella ja saman verkkotunnuksen takana. Tällöin tavallinen dynaaminen Next.js tarvitsee oman Node-prosessin/kontin Laravelin rinnalle. Next.js:n täytyy edelleen saada data Laravelilta esimerkiksi sisäisten HTTP-reittien kautta. Yhteinen domain voi poistaa tarpeen erilliselle cross-origin/CORS-järjestelylle, mutta ei tiedonsiirtorajapintaa tai käyttöoikeustarkistuksia. Next.js:n server component tai proxy voi piilottaa yhteyden selaimelta, ei poistaa sitä. [Next.js: Backend for Frontend](https://nextjs.org/docs/app/guides/backend-for-frontend)

Staattinen Next.js-export voi poistaa Node-ajopalvelimen, mutta muuttuvat hinnat ja tapahtumat tarvitsevat silloin edelleen dynaamisen tietolähteen. Yhteinen suora tietokantakäyttö kahdesta sovelluksesta hajauttaisi tämän projektin liiketoimintasäännöt eikä ole suositus.

Bladella ensimmäinen HTML syntyy suoraan Laravelin palveluista. Sen jälkeen pieni saman originin JSON-reitti päivittää muuttuneet tiedot ilman sivun välkkymistä. Se on teknisesti API, mutta kuuluu samaan sovellukseen ja julkaisuun: erillistä API-hostia tai julkista integraatiotuotetta ei tarvita. Aiemman speksin `/api/v1/display` tarkoittaa juuri tätä.

Päätös toteutusohjeissa: jatketaan Blade + CSS + pieni JavaScript -ratkaisulla. Käyttäjän kysymys Next.js:stä on arvioitu vaihtoehtona, ei tulkittu vahvistetuksi teknologianvaihdoksi. Next.js:ään voi palata, jos myöhemmin tarvitaan huomattavasti monimutkaisempi React-käyttöliittymä.

## Raspberry Pi: valmis kioskitoiminto

Omaa kioskisovellusta ei tehdä. Raspberry Pi OS Desktopin Chromium voidaan avata `--kiosk`-tilaan. Tarvitaan asennus-/käynnistyskonfiguraatio, laiteparitus, luotettu varmenne, näytön virta-asetukset ja kaatumisesta palautuminen. Tarkka autostart asetetaan käytössä olevan Pi OS -version työpöytäympäristön mukaan. [Raspberry Pi:n virallinen kioskiohje](https://www.raspberrypi.com/tutorials/how-to-use-a-raspberry-pi-in-kiosk-mode/)

Tässä projektissa yksi välilehti riittää; virallisen esimerkin välilehtien vaihtoskriptiä tai näppäinpainallusten simulointia ei tarvita. Mahdollinen pieni paikallinen yhteysvirhesivu ja käynnistysvalvonta ovat tukitoimintoja, eivät oma selain. Kiosk-tila piilottaa selaimen käyttöliittymää, mutta ei korvaa käyttöjärjestelmän ja sovelluksen suojausta.

## Samsung SM-T555

Käyttäjän vahvistama mallikoodi on **SM-T555**, eli Samsung Galaxy Tab A 9,7″ / 4G. Aiempi maininta Galaxy Tab 3:sta korvataan tällä. [Samsungin mallisivu](https://www.samsung.com/uk/support/model.SM-T555NZKABTU/)

Samsungin virallisessa kyseisen mallin päivityshistoriassa näkyy Android 7.1.1. Tämä ei todista käyttäjän laitteen nykyistä asennettua versiota. Nykyisen Chromen virallinen vähimmäisvaatimus on Android 10, joten Android 7.1.1:een jäävä laite ei täytä uusimman selaimen vaatimusta. [Samsungin päivityshistoria](https://doc.samsungmobile.com/SM-T555/004652190404/wel.html) · [Chromen järjestelmävaatimukset](https://support.google.com/chrome/answer/95346/download-and-install-google-chrome-android?co=GENIE.Platform%3DAndroid&hl=en-GB)

Johtopäätös: tabletti on hyödyllinen olemassa oleva laite asettelun ja käyttötavan kokeilemiseen. Sitä ei vielä hyväksytä pysyväksi perhetietoja näyttäväksi tuotantolaitteeksi. Jos käyttöjärjestelmää ja selainta ei voida pitää tuettuna, ensisijainen pysyvä ratkaisu on Pi tai uudempi tuettu tabletti. Kevyt Blade-sivu voi toimia vanhassa selaimessa, mutta pelkkä toimivuus ei tarkoita ajan tasalla olevaa selainta.

Ensimmäinen testi ei vaadi erillistä kioskisovellusta: avataan testisivu nykyiseen selaimeen. Sovelluksen kiinnitys ja näytön päälläpito tarkistetaan laitteen omista asetuksista, eikä nykyisen One UI:n ominaisuuksia oleteta tämän mallin ominaisuuksiksi. Mahdollinen kioskiselain valitaan vasta Android-version selvittyä virallisesta ylläpidetystä jakelusta. PWA-asennusta, Screen Wake Lockia, automaattista uudelleenkäynnistystä tai Android WebView'n erillistä päivitettävyyttä ei oleteta.

## Tabletin koekäytön tarkistuslista

1. Kirjaa asennettu Android-versio, tietoturvapäivityksen päivä, selaimen nimi ja tarkka versio. Selvitä saatavilla olevat viralliset päivitykset.
2. Avaa synteettistä testidataa näyttävä sivu kotipalvelimen aidolla HTTPS-osoitteella. Tarkista varmenteen luottamus ilman virheen ohittamista sekä laiteistunnon evästeet.
3. Mittaa CSS-viewport vaaka- ja pystyasennossa. Valitse pienelle tabletille lähempää luettava profiili ja vähemmän listarivejä. Suuren seinänäytön kahden metrin tavoitetta ei automaattisesti sovelleta 9,7 tuuman ruutuun.
4. Tarkista CSS Grid, CSS-muuttujat, numero-/aikamuotoilu ja päivitysskriptit oikealla selaimella. Keskustelun ulkoasuluonnoksen moderni `light-dark()`-CSS ei ole yhteensopivuuslupaus; toteutuksessa käytetään tarvittaessa tavallisia teemaluokkia. Älä alenna palvelimen riippuvuuksia tabletin vuoksi.
5. Varmista, että herääminen/lepotila ja Wi-Fi-katkos eivät jätä vanhaa vihreää sähkösuositusta näkyviin. Puuttuva JS-toimivuus on este dynaamisen liikennevalon hyväksynnälle, vaikka HTML näkyisi.
6. Tarkista näytön päälläpito, paluu sivulle selaimen sulkeuduttua ja yön aikainen toiminta. Aloita lyhyellä valvotulla kokeella; hyväksytylle laitteelle lopuksi 48 tunnin vakauskoe. Tarkista vanhan akun kunto ennen jatkuvaa latauksessa käyttöä.

Hallintaa ja Google-valtuutusta käytetään ajantasaisella tietokoneella tai puhelimella. Näyttölaite saa vain oman rajatun näyttöistuntonsa. Testi ei vaadi Google-tilin kirjautumista tabletissa.
