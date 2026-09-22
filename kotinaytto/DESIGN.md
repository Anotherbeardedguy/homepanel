# Kotinäytön käyttöliittymä

22.09.2026 — toteutusta ohjaava suunnitelma. Käyttöliittymä on kokonaan suomeksi.

## Suunta: rauhallinen kodin tilannekuva

Näyttöä luetaan ohimennen noin kahden metrin päästä. Kolmessa sekunnissa pitää ymmärtää sähkön hintatila ja seuraava meno. Kymmenessä sekunnissa ehtii tarkistaa sään ja kotityöt. Keskeinen suunnitteluratkaisu on erikokoiset sisältöalueet: sähkö saa pääroolin, sää tukee päivän suunnittelua, tapahtumat ja työt muodostavat luettavat listat.

Impeccablen ohjeista sovelletaan sisällön tärkeysjärjestystä, tarkoituksellista ryhmittelyä, erottuvia tekstirooleja ja poikkeustilojen suunnittelua. Alla olevat mitat ja värit ovat tämän projektin omia suunnittelupäätöksiä, eivät Impeccablen valmis teema. Ohjeisiin tutustuttiin verkossa; Impeccable-pakettia ei asennettu eikä sen automaattista auditointia ajettu. [Suunnitteluprosessi](https://impeccable.style/designing/) · [Asettelu](https://impeccable.style/docs/layout/) · [Typografia](https://impeccable.style/docs/typeset/) · [Poikkeustilat](https://impeccable.style/docs/harden/)

## Sommittelu

1920 × 1080 vaakasuuntainen näyttö, 100 % selainzoomaus:

- Ulkomarginaali 56 px. Yläpalkki noin 112 px: vasemmalla viikonpäivä ja päivämäärä, oikealla kello. Ei sekunteja, navigaatiota tai tervetulotekstiä.
- Yläalue noin 400 px: sähkö 60 % leveydestä, sää 40 %. Alueiden väli 40 px. Sähköalueella hienovarainen tilan sävy ja 20 px kulmat. Sää asettuu suoraan sivun taustalle.
- Alaosa noin 360 px: tapahtumat ja kotityöt, sama sarakejako. Erottelu tyhjällä tilalla ja ohuilla vaakaviivoilla. Listariveillä ei omia kortteja.
- Alareunassa varattu tila yhteysilmoitukselle. Normaalitilassa ei jatkuvia teknisiä tilatunnisteita. Sää- ja hintalähde sekä päivitysaika ovat kyseisen sisällön yhteydessä.
- Kokonaiskorkeus sovitetaan näytön todelliseen CSS-kokoon. Vähennetään listojen rivejä ennen tekstin pienentämistä. Ei automaattista vieritystä tai karusellia.

```text
Tiistai 22.09.2026                                        16.30

┌────────────────────────────────────┐
│ Sähkö nyt         ● Edullinen      │  Lahti
│                                    │  +12°   Puolipilvistä
│ Nyt on hyvä                        │  Tuntuu +10° · Tuuli 3 m/s
│ aika pyykille.                     │  Illalla sadetta
│                                    │
│ 4,20 snt/kWh · 16.30–16.45         │  Foreca · Päivitetty 16.20
│ Spot, ei ALV:tä                    │
└────────────────────────────────────┘

Tänään                                 Kotityöt
17.00  Jalkapalloharjoitus               Tyhjennä astianpesukone
       Aino · Kenttä                    Aino · Tänään
18.30  Vanhempainilta                    Vie roskat
       Koulu                            Kuka ehtii · Tänään
```

Esimerkkien tapahtumat, henkilöt, hinnat ja sää ovat keksittyjä. Luonnos ei ole reaaliaikainen näkymä.

## Typografia ja mitoitus

Yksi paikallinen groteski: Raspberry Pi OS:n Noto Sans, jos saatavilla; muuten `system-ui, sans-serif`. Toteutuksessa varmistetaan sama fontti hallinnan esikatseluun paikallisesti lisenssin sallimalla tavalla. Ei verkkofonttikutsuja. Painot 400 ja 600, hintaan ja kelloon tasalevyiset numerot (`tabular-nums`).

| Rooli | 1080p-kioskin lähtökoko | Käyttö |
|---|---|---|
| Sähkön käyttöohje | 64 px / riviväli 1,1 | Enintään 2–3 lyhyttä riviä |
| Lämpötila ja kello | 72 px / 1,05 | Rauhallinen numero, selvästi näkyvä yksikkö |
| Sähkön hinta | 48 px / 1,15 | Samassa ryhmässä yksikkö ja voimassaolo |
| Tapahtuma ja kotityö | 32 px / 1,3 | Korkeintaan 2 riviä |
| Otsikko ja tilasana | 28 px / 1,3 | Sähkö nyt, Tänään, Kotityöt |
| Täydentävä tieto | 24 px / 1,4 | Paikka, vastuuhenkilö, hintaperuste ja päivitys |

Välit: 8, 16, 24, 40 ja 56 px. Nämä ovat lähtömittoja oikealle seinänäytölle, eivät lupaus luettavuudesta kaikilla näytön tuumakooilla. Pienemmässä keskusteluluonnoksessa kokoja sovitetaan käytettävään leveyteen. Varsinainen luettavuus tarkistetaan valitulla laitteella.

## Värit

Väri kertoo sähkön tilasta. Muu sisältö pysyy lähes yksivärisenä. Päiväteema on oletus, yöteema vaihdetaan hallinnan aikataululla tai käsin. Koko ruutu ei vaihda sähkön väriseksi.

| Tunniste | Päivä | Yö |
|---|---|---|
| Sivun tausta | #F5F7F8 | #141C21 |
| Pääteksti | #18252B | #F1F5F7 |
| Toissijainen teksti | #4C5B64 | #B5C2C9 |
| Erotin | #CCD5DA | #38464E |
| Edullinen: teksti/valo | #176340 | #86D7AD |
| Edullinen: pinta | #E5F1E9 | #19372B |
| Tavallinen: teksti/valo | #75500B | #EBC576 |
| Tavallinen: pinta | #F6ECCD | #392E18 |
| Kallis: teksti/valo | #9E332A | #FFB4A8 |
| Kallis: pinta | #F8E8E4 | #432522 |
| Tuntematon: pinta | #E7ECEF | #29353D |

Tilavalona yksi täytetty ympyrä ja aina tilasana. Harmaassa tilassa ympyrän sijasta viiva/katkosmerkki ja ”Ei hintatietoa”. Sivun muut varoitukset eivät käytä liikennevaloväriä pelkkänä koristeena. Tavoite: tekstit vähintään 4,5:1; olennaiset graafiset merkit vähintään 3:1. Kontrastit tarkistetaan toteutuksen todellisista väriyhdistelmistä. Varjoja, liukuvärejä ja lasipintoja ei tarvita tässä sommittelussa.

## Sisältö ja vuorovaikutus

Sähkö: tilasana yläreunassa, lyhyt tekemisehdotus pääotsikkona, hinta ja voimassaolo alla. Vihreä ”Nyt on hyvä aika pyykille.” Keltainen ”Käytä sähköä tavalliseen tapaan.” Punainen ”Pyykki voi odottaa. Lähtisitkö ulos?” Täydentävä viesti ”Sammuta turhat valot” voidaan näyttää punaisessa tilassa. Tekstit ovat hallittavia; esikatselu varoittaa liian pitkästä ohjeesta. Nykyinen halpa vartti ei lupaa halpaa koko pesuohjelmaa; myöhemmän ajoitusominaisuuden tieto näytetään erikseen.

Sää: paikkakunta, lämpötila, yksinkertainen sääsymboli ja sanallinen tila. Alle tuntuu kuin ja tuuli sekä yksi hyödyllinen ennusterivi, jos data tukee sitä. Ei taustavalokuvaa eikä animoitua sadetta. Tuotannon sääsymbolit valitaan Forecan käyttöehtojen mukaan.

Kalenteri: kellonajat omassa tasalevyisessä sarakkeessa, otsikot vasempaan reunaan. Käynnissä oleva ja seuraava tapahtuma ensin; koko päivän tapahtumat omana rivinään ja menneet tämän päivän tapahtumat listan lopussa. Enintään 3 tapahtumariviä oletuskoossa. Lisämäärä tekstinä ”Lisäksi 2 tapahtumaa”. Pitkä otsikko saa kaksi riviä; hallinta voi antaa erillisen lyhyen näyttöotsikon muuttamatta Google-tapahtumaa. Katkaisu merkitään …, eikä koko tietoa luvata seinänäytöllä.

Kotityöt: työ ja vastuuhenkilö, oletuksena 3 riviä. Myöhästymisestä selvä sana ”Myöhässä”, ei pelkkä punainen väri. Katselutilan rivit eivät näytä painikkeilta tai klikattavilta valintaruuduilta. Kosketustila tuo erillisen ”Valmis”-painikkeen, vähintään 48 × 48 CSS-px; onnistuneen kuittauksen jälkeen ”Tehty” ja mahdollisuus perua. Ei suoritusten tulostaulua.

Tekstin päivitys ei siirrä käyttäjän katsetta: listat vaihtuvat kerralla, kellon leveys pysyy, sähkötilan vaihtuminen ei muuta alueen kokoa. Ei vilkkumista, lähtöanimaatiota tai jatkuvaa liikettä. Ruudunlukijalle ilmoitetaan merkityksellinen tilan muutos, ei jokaisen kellopäivityksen lukemista.

## Poikkeustilat

| Tilanne | Näytön käyttäytyminen |
|---|---|
| Ensimmäinen lataus | Alueen otsikko ja ”Haetaan sähkön hintaa…”; varataan lopullisen sisällön tila, ei välkkyvää latauspintaa |
| Nykyhinta puuttuu | Harmaa sähköalue, hinnan tilalla —, ”Hintatieto puuttuu”. Käyttösuositus poistuu |
| Sää vanhentunut | ”Säätieto voi olla vanhentunut · Päivitetty 14.20”; vanhenemisrajan ylityttyä lämpötila poistuu |
| Ei menoja | ”Ei tapahtumia tänään” onnistuneen synkronoinnin jälkeen |
| Työt tehty | ”Päivän kotityöt on tehty.” Ei tyhjää taulukkoa |
| Kalenteriyhteys rikki | ”Kalenteria ei voitu päivittää” ja viimeinen onnistuminen; ei virheellistä tyhjän päivän väitettä |
| Kotipalvelin poissa | Alareunan pysyvä viesti ”Yhteys kotipalvelimeen katkesi. Yritetään uudelleen.”; tietokohtaiset voimassaoloajat säilyvät käytössä |
| Oikeus poistettu | Sisältö peitetään ja ”Näyttö täytyy yhdistää uudelleen” |

Katselunäytöllä ei ole turhaa Yritä uudelleen -painiketta: yhteys palautetaan automaattisesti. Hallinnassa on korjaustoiminnot. Oletuksena hiljaiset ajat 22.00–06.30 ovat käyttöönotossa hyväksyttävä ehdotus, eivät jo sovittu perheen aikataulu.

## Eri näytöt ja hallittavuus

Pystynäytöllä järjestys on sähkö, sää, tapahtumat, työt; listojen määrä sovitetaan korkeuteen. Puhelimen esikatselu saa vierittyä, seinänäyttö ei. Hallinta tarjoaa valmiit profiilit ”Vaaka”, ”Pysty” ja ”Suuri teksti”. Kortteja voi piilottaa ja järjestää; tyhjä tila annetaan muille alueille. Vapaita pikselikoordinaatteja ei tarvita ensimmäiseen versioon.

Hallinnan näyttöprofiiliin tallennetaan teema, tekstikoko, asetteluprofiili, korttijärjestys, rivimäärät ja lyhyet ohjetekstit. Esikatselu käyttää samoja Blade-komponentteja ja CSS:ää kuin Pi. Hintatiloja, puuttuvaa tietoa ja pitkää tapahtumaa voi esikatsella testidatalla muuttamatta tuotantodataa. Tarkat kontrastivärit pysyvät testattuina teemoina; käyttäjä ei vahingossa muuta hintatilan merkitystä vapaalla värivalinnalla.

## Toteutus ja suunnittelun tarkistus

Blade-komponentit: näyttökehys, kello, sähköalue, sääalue, tapahtumalista, kotityölista ja yhteysilmoitus. CSS Grid, CSS-muuttujat ja pieni JavaScript päivittävät sivua. Vuea, Nextiä, Reactia tai SPA-reititystä ei käytetä.

Toteutuksen hyväksyntä: 1920 × 1080, 1280 × 720 ja 1080 × 1920; esikatselu 390 px ja 200 % zoomauksella. Testaa −12,50 ja 125,00 snt/kWh, kaikki neljä hintatilaa, kaksi riviä pitkä otsikko, 0/1/10 tapahtumaa, puuttuva vastuuhenkilö, verkkokatko ja tumma teema. Pääohje ja seuraava tapahtuma tunnistetaan oikealla näytöllä 3 sekunnissa kahden metrin päästä. Tarkistus tehdään myös harmaasävyisenä. Tämä dokumentti ei väitä näitä laitetestejä tehdyiksi.
