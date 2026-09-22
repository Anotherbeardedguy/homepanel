# Toteutus Cursorissa: kotinäyttö ja näyttölaitteet

Päivitetty 22.09.2026. Käytä samaa Laravel-projektia kuin hallintapaneelissa. Aloita hallintapaneeli/CURSOR-GROK.md:n palvelinrungosta; tämä työ jatkaa sitä.

## Cursorin valmistelu

Valitse Agent-tilassa Grok 4.7, joka on 22.09.2026 Cursorin virallisessa malliluettelossa uusin Grok-versio. Saatavuus tarkistetaan omasta mallivalitsimesta; ohjetiedosto ei vaihda mallia. Myöhemmin tarkista mallin ajantasaisuus uudelleen. [Cursorin malliluettelo](https://cursor.com/docs/models-and-pricing)

Avaa yhteinen projektijuuri ja liitä keskusteluun tämä tiedosto, DESIGN.md, SUUNNITELMA.md, LAITTEET-JA-ARKKITEHTUURI.md sekä hallintapaneeli/SPEKSI.md. Liitä lisäksi toteutetun sovelluksen README, näyttöreittien sopimus ja TOTEUTUKSEN-TILA.md, jos ne ovat olemassa. Näin agentti ei keksi toista palvelinta tai ristiriitaista API:a.

## Kopioitava aloitusprompti

```text
Toteuta suomenkielinen kotinäyttö tämän projektin olemassa olevaan
Laravel-sovellukseen. Lue ensin liitetyt dokumentit, soveltuvat säännöt,
Git-tila sekä nykyiset reitit, komponentit ja testit.

Kotinaytto/ on suunnittelukansio. Varsinainen toteutus kuuluu samaan
sovellus/-Laravel-projektiin kuin hallinta. /display on Blade-sivu.
Käytä semanttista HTML:ää, omaa CSS:ää ja vain tarvittavaa JavaScriptiä.
Ei Next.js:ää, Vuea, Reactia, erillistä frontend-palvelinta tai omaa
kioskisovellusta. Toteuta jaettu näkymämalli, jota Blade-alkurenderöinti
ja saman originin suojattu päivitysreitti käyttävät.

Noudata DESIGN.md:n hierarkiaa: sähkön käyttöohje on pääasia,
sää rinnalla, päivän tapahtumat ja kotityöt alla. Kaikki tekstit suomeksi.
Lähtöasetukset Lahti ja Google Calendar, mutta sisältö ja näyttöprofiili
luetaan hallinnasta. Älä kovakoodaa perheen nimiä, kotitöitä tai säätä.
Impeccable on suunnittelureferenssi, ei automaattisesti asennettu työkalu.
Tutustu sen virallisiin layout-, typeset- ja harden-ohjeisiin. Älä väitä
ajaneesi Impeccable-auditointia, ellei työkalu oikeasti ole käytössä.

Pi käyttää valmista Chromium-kioskia. Vaihtoehtoinen Galaxy Tab A SM-T555
vaatii ensin mallin, Androidin, selaimen ja HTTPS-yhteyden tarkistuksen.
Älä oleta modernin CSS:n, JavaScriptin tai Androidin kioskitoimintojen
toimivan vanhassa tabletissa. Toteuta ensin turvallinen, synteettistä
testidataa näyttävä selainkoe ja kirjaa tulos. Älä heikennä TLS:ää,
poista selaimen sandboxia tai asenna tuntemattomia APK-tiedostoja.

Toteuta aidot lataus-, tyhjä-, virhe-, vanhentumis- ja yhteyskatkotilat.
Sähkön puuttuva tai vanhentunut vartti ei saa jäädä vihreäksi edes
verkkokatkossa. Näytä lähde, hintaperuste ja voimassaolo. Sivun herätessä
tarkista ajantasaisuus välittömästi. Ei koko sivun säännöllistä reloadia,
välkkyviä animaatioita tai kosketusta vaativaa tietoa katselutilassa.

Tee komponentit, responsiivinen CSS, pienet päivitysskriptit ja tarpeelliset
testit. Tarkista oikeat renderöidyt sivut selaimella. Älä hyväksy pelkkää
koodin lukemista visuaaliseksi testiksi. Raportoi mitatut näyttökoot,
testatut tilat ja oikealla Pi:llä/tabletilla vielä tarkistamatta olevat asiat.
```

## Toteutuksen eteneminen

1. **Selainkoe:** testisivu ilman perhetietoja. Sama HTTPS, fontit, evästeet ja keskeiset JS/CSS-ominaisuudet kuin lopullisessa sovelluksessa. Galaxy Tab A SM-T555:n tuki ratkaistaan tulosten perusteella, ei mallinimen perusteella.
2. **Blade-alkunäkymä:** neljä osiota palvelimelta, suojattu laiteistunto ja hallinnan julkaistu profiili. JavaScriptin latausvirhe ei tuota tyhjää ruutua. Alkusisällön viimeinen päivitysaika on aina näkyvissä.
3. **Päivittyminen:** yksi hallittu pollauskierros kerrallaan, aikakatkaisu ja viive kasvavilla uudelleenyrityksillä. Ei rinnakkaisia pyyntöjonoja. Sähkön varttiraja tarkistetaan paikallisesti palvelimen aikapoikkeama huomioiden. Pelkkä timer ei riitä laitteen palatessa lepotilasta.
4. **Visuaalinen viimeistely:** tekstiroolit, päivä/yö, pitkät otsikot, suuret/negatiiviset hinnat, rajatut listat ja puuttuvat tiedot. Sovita todelliseen CSS-viewportiin; fyysinen 1280 × 800 paneeli ei välttämättä tarkoita 1280 CSS-pikseliä.
5. **Hallinnan esikatselu:** samat Blade-komponentit ja tyylit kuin kotinäytössä. Erillinen merkitty testidatatila ei muuta aitoa hintaluokitusta tai tapahtumia.
6. **Kioskiasennuksen ohje:** Pi OS:n version mukainen autostart, Chromium, luotettu TLS, uudelleenkäynnistysvalvonta ja näkyvä palautuminen yhteysvirheestä. Ei selaimen rakentamista. Tabletille ohje vain varmennettuihin toimintoihin.

## Testimatriisi

| Asia | Vähimmäistarkistus |
|---|---|
| Asettelu | 1920 × 1080, 1280 × 720, 1080 × 1920, 390 px esikatselu ja todellinen tablettiviewport |
| Sähkö | Neljä tilaa, raja-arvot, negatiivinen hinta, puuttuva jakso, vartin vaihtuminen offline |
| Sisältö | 0/1/10 tapahtumaa, pitkä suomenkielinen otsikko, kaikki työt tehty, puuttuva vastuuhenkilö |
| Saavutettavuus | Kontrastimittaus, merkitys ilman väriä, näppäimistö, 200 % zoomaus, liikkeen vähennys |
| Palautuminen | Palvelin poissa käynnistyksessä, verkon paluu, selaimen kaatuminen, laiteistunnon peruutus |
| Tabletin elinkaari | Lepotilasta herääminen, Wi-Fi-katko, latauksessa käyttö, kierto, selaimen käynnistys uudelleen |
| Fyysinen käyttö | Katseluetäisyys sovittu näytön koon mukaan; 48 h vakauskoe hyväksytyllä laitteella |

Katselulaite ei kuittaa kotitöitä ellei oikeus ole erikseen sallittu. Tabletin kosketustuki ei automaattisesti anna kirjoitusoikeutta. Vanhan selaimen yhteensopivuuskerros ei korjaa selaimen tietoturvatukitilannetta.

## Jatkamisviesti keskeytyksen jälkeen

```text
Lue TOTEUTUKSEN-TILA.md, tämä toteutusohje ja nykyinen Git-diff.
Varmista mikä jo toimii ja jatka ensimmäisestä keskeneräisestä
hyväksymiskriteeristä. Älä rakenna sovellusta uudelleen. Päivitä lopuksi
toteutuksen tila, toistettavat testikomennot ja avoimet laitetestit.
```

