# Kodin yhteinen näyttö — Raspberry Pi

Suunnitelma 22.09.2026. Kieli ja alue: suomi / Suomi. Aikavyöhyke: Europe/Helsinki.
Tämä on toteutussuunnitelma, ei vielä toimiva sovellus.

## Tavoite ja kokonaisuus

Laitevaihtoehtojen tarkennus: käyttäjällä on myös Samsung Galaxy Tab A 9,7″ SM-T555. Se on selaimen yhteensopivuustestattava vaihtoehto Pi:lle. Palvelin ja näyttö erotetaan laiteriippumattomasti; katso [laitteet ja arkkitehtuuri](LAITTEET-JA-ARKKITEHTUURI.md) sekä [Cursor-toteutusohje](CURSOR-GROK.md).

Keittiön tai eteisen näyttö kertoo yhdellä silmäyksellä sähkön hintatilanteen, Lahden sään, tämän päivän perhetapahtumat ja kotityöt. Kaikki sisältö ja asetukset muutetaan kotiverkon Laravel-hallintapaneelista. Raspberry Pi avaa saman sovelluksen näyttönäkymän Chromiumin kiosk-tilassa.

Kaksi käyttöliittymää, yksi palvelin ja tietokanta: hallinta `/admin`, kotinäyttö `/display`. Näytölle ei asenneta omaa liiketoimintalogiikkaa eikä ulkopuolisten palvelujen API-avaimia. Toteutuksen lähdekoodi pidetään yhdessä Git-repositoriossa; nämä kaksi kansiota erottavat suunnitteludokumentit.

Vahvistettu: sääpaikka Lahti, kalenteri Google Calendar, kaikki säädettävissä hallinnasta. Oletus: ensimmäinen näyttö on katselunäyttö, Raspberry Pi 4/5 ja vaakasuuntainen 1920 × 1080 näyttö. Pi-malli, resoluutio ja kosketustuki tarkistetaan ennen laiteasennusta. Käyttöliittymä tukee myös pystysuuntaa. Erillinen fyysinen liikennevalo on mahdollinen jatkokehitys; ensimmäinen versio näyttää valon ruudulla.

## Näytön rakenne

Tarkennettu käyttöliittymäsuunnitelma: [DESIGN.md](DESIGN.md). Se määrittää lopullisen visuaalisen hierarkian: sähköalue saa noin 60 % yläosan leveydestä ja muita alueita ryhmitellään tyhjällä tilalla. Alla oleva tasajakoinen kaavio on alkuperäinen sisältökartta, ei lopullinen ulkoasu.

```text
┌──────────────────────────────────────────────────────────┐
│ Tiistai 22.09.2026                     16.30       Lahti  │
├───────────────────────────┬──────────────────────────────┤
│ SÄHKÖ NYT                 │ SÄÄ                          │
│ ● Edullinen               │ +12 °C  Puolipilvistä         │
│ 4,20 snt/kWh              │ Tuntuu +10 °C · Tuuli 3 m/s   │
│ Voimassa 16.30–16.45      │ Sadetta iltapäivällä          │
│ Nyt on hyvä aika pyykille │ Lähde: Foreca                 │
├───────────────────────────┼──────────────────────────────┤
│ TÄNÄÄN                    │ KOTITYÖT                     │
│ 17.00 Jalkapalloharjoitus  │ □ Tyhjennä astianpesukone     │
│ 18.30 Vanhempainilta       │ □ Vie roskat                 │
└───────────────────────────┴──────────────────────────────┘
```

Arvot ovat havainnollistavia esimerkkejä, eivät todellisia hinta-, sää- tai perhetietoja. Hallinnassa voi piilottaa, järjestää ja mitoittaa neljä korttia, vaihtaa otsikot ja teeman sekä esikatsella asettelun. Yksi selkeä ruutu ilman jatkuvaa automaattista sivunvaihtoa. Pitkästä listasta näytetään sovittu määrä ja esimerkiksi ”Lisäksi 3 tapahtumaa”.

## 1. Sähkön liikennevalo

Tieto on Suomen tarjousalueen day-ahead-spot-hinta. Se ei kuvaa sähköverkon kuormitusta, sähkökatkon uhkaa eikä sellaisenaan koko sähkölaskua. Hintadata käsitellään 15 minuutin jaksoissa. Nord Poolin virallinen tiedote vahvistaa varttimarkkinan aloituksen toimituspäivälle 01.10.2025. [Lähde](https://www.nordpoolgroup.com/en/message-center-container/newsroom/exchange-message-list/2025/q3/market-coupling-steering-committee-confirms-go-live-of--15-minute-mtu-in-sdac-on-trading-day-30-september-2025-for-delivery-day-1-october-2025/)

Ensisijainen integraatio on ENTSO-E Transparency Platformin virallinen rajapinta. Ennen toteutuksen hyväksyntää hankitaan API-käyttöoikeus, varmistetaan Suomen varttidatan saatavuus testivastauksesta sekä tietojen näyttämisen ja säilytyksen ehdot. Vaihtoehtona on Nord Poolin sopimuspohjainen Market Data API. Epävirallisia hintasivujen rajapintoja tai HTML-poimintaa ei käytetä. [ENTSO-E](https://www.entsoe.eu/data/transparency-platform/) · [API-tunnuksen hankinta](https://transparencyplatform.zendesk.com/hc/en-us/articles/12845911031188-How-to-get-security-token)

Ensiversion luokittelu perustuu kiinteisiin, hallittaviin rajoihin. Esimerkkiasetukset, jotka hyväksytään käyttöönotossa:

| Tila | Valitun hintaperusteen arvo | Oletusviesti |
|---|---|---|
| Vihreä / Edullinen | alle 8,00 snt/kWh | Nyt on hyvä aika pyykille ja muille sähkötöille. |
| Keltainen / Tavallinen | vähintään 8,00 mutta alle 15,00 snt/kWh | Käytä sähköä normaalisti. Suuret sähkötöiden aloitukset voi ajoittaa. |
| Punainen / Kallis | vähintään 15,00 snt/kWh | Siirrä pyykki ja sauna myöhemmäksi. Nyt voisi lähteä ulos — sammuta turhat valot. |
| Harmaa / Ei hintatietoa | nykyistä jaksoa ei tunneta tai tieto on virheellinen | Ajantasainen sähkön hinta ei ole saatavilla. |

Väriä täydentävät aina sana ja kuvake. Hallinta muuttaa kaikkia viestejä ja rajoja; vihreän rajan on oltava pienempi kuin punaisen. Negatiiviset hinnat sallitaan. Luokittelu käyttää tarkkaa arvoa ennen näyttöpyöristystä.

Hintaperuste valitaan ja nimetään näkyvästi: veroton spot, spot + ALV tai arvioitu energiahinta (spot + marginaali + ALV). Siirto ja kiinteät maksut eivät sisälly näihin. Veroprosentti ja marginaali ovat asetuksia, eivät koodiin lukittuja arvoja. Hallinta kertoo, syötetäänkö marginaali verottomana. ALV-arvo vahvistetaan käyttöönotossa; puuttuvalla arvolla ei esitetä verollista hintaa.

Muunnos: EUR/MWh ÷ 10 = snt/kWh. Verollinen energiahinta = (veroton spot snt/kWh + veroton marginaali snt/kWh) × (1 + ALV/100). Käytetään desimaalilaskentaa tai skaalattuja kokonaislukuja, ei liukulukurahaa. Jos lähde sisältää veron, sitä ei lisätä uudelleen. Lähteen yksikkö, valuutta ja verokäsittely validoidaan.

Nykyinen jakso valitaan ehdolla `start <= nyt < end`. Ajat tallennetaan UTC:ssa ja näytetään Suomen ajassa. Kesäaikapäivää ei oleteta 96 vartin mittaiseksi: se voi sisältää 92 tai 100 jaksoa. Aineiston katkoja ei täytetä edellisellä hinnalla.

Pelkkä halpa vartti ei tarkoita halpaa kahden tunnin pesua. Lisätieto ”Edullisin 2 tunnin aloitus tänään” lasketaan vain yhtenäisestä julkaistusta datasta, aikapainotettuna keskiarvona. Se merkitään tasaisen kulutuksen arvioksi. Kuluvan vartin väri ja pidemmän jakson suositus pidetään erillään. Ensiversion päätoiminto on nykyhinta; pidemmän jakson laskenta voidaan tehdä seuraavassa vaiheessa.

Palvelin hakee tämän ja seuraavan päivän hinnat 60 minuutin välein, elleivät palvelun käyttöehdot vaadi harvempaa tahtia. Julkaisematon huominen on normaali tila. Käynnistys ja hallinnan Päivitä nyt voivat käynnistää rajoitetun lisähaun. Jo haettu tuleva vartti toimii verkkokatkossa, jos sen aikaväli on voimassa. Muuten valo vaihtuu harmaaksi, myös ruudun ollessa offline. Käytetään palvelimen aikaleimaa ja havaitaan kellon huomattava poikkeama.

## 2. Sää

Oletuspaikka Lahti. Hallinnan paikkahaku valitsee palvelun sijaintitunnuksen/koordinaatit ja näyttää esikatselun. Foreca valitaan ensimmäiseksi palveluksi; AccuWeather jää vaihtoehdoksi, joka vaatii oman varmennetun integraation ennen käyttöönottoa.

Kortti näyttää lämpötilan, tuntuu kuin -arvon, sääsymbolin ja suomenkielisen kuvauksen, tuulen m/s sekä päivän ennusteen ja sateen todennäköisyyden saatavuuden mukaan. Puuttuva sateen todennäköisyys ei tarkoita nollaa. Havainto ja ennuste nimetään erikseen. Forecan virallinen API tukee suomen kieltä ja vaatii API-avaimen sekä sopivan tilauksen. Lähdemerkintä ja symbolien käyttö toteutetaan lisenssin mukaisesti. [Forecan dokumentaatio](https://developer.foreca.com/)

Oletuspäivitys 30 minuuttia; taajuus rajoitetaan tilauksen kiintiöön. Näytöllä viimeinen onnistunut päivitys. Yli 90 minuuttia vanhassa tiedossa merkintä ”Säätieto voi olla vanhentunut”; yli 6 tuntia vanha nykytila piilotetaan. Ennusteella on oma voimassaoloaikansa. Raja-arvot ovat hallittavia. Avainta ei toimiteta Pi:lle.

## 3. Tämän päivän perhetapahtumat

Google Calendar yhdistetään hallinnassa vain lukuoikeudella. Hallinnassa valitaan yksi tai useampi kalenteri, nimet, järjestys, näkyvyys ja henkilöiden tunnisteet. Näyttö näyttää otsikon, kellonajan tai ”Koko päivä” ja valinnaisen paikan. Kuvaukset, osallistujien sähköpostit ja kokouslinkit ovat oletuksena piilossa.

Yksityisen tapahtuman otsikko korvataan oletuksena tekstillä ”Varattu”. Hallinnassa voidaan sallia valitun kalenterin otsikot erikseen. Näytetään kaikki tämän paikallisen päivän kanssa päällekkäiset tapahtumat, myös yön yli jatkuvat, koko päivän ja toistuvien tapahtumien poikkeukset. Päivämääräpohjainen koko päivän tapahtuma ei muutu UTC-muunnoksessa edelliselle päivälle.

Taustahaku 5 minuutin välein, sivutus ja toistuvat esiintymät mukaan. Peruutetut ja poistetut tapahtumat poistuvat onnistuneessa synkronoinnissa. Yli 15 minuuttia vanhassa tiedossa varoitus, yli 24 tunnin tiedot piilotetaan. Onnistunut tyhjä haku näyttää ”Ei tapahtumia tänään”; epäonnistunut haku näyttää yhteysvirheen. [Google Events.list](https://developers.google.com/workspace/calendar/api/v3/reference/events/list)

## 4. Kotityöt

Otsikko, vastuuhenkilö tai ”Kuka ehtii”, eräpäivä, mahdollinen kellonaika, tärkeys ja tila. Hallinta luo kertaluonteiset, päivittäiset ja valittuina viikonpäivinä toistuvat työt. Myöhässä olevat näkyvät ennen tänään erääntyviä. Tehty työ poistuu oletuksena näytöltä; historia säilyy hallinnassa määritellyn ajan.

MVP: kuittaus puhelimella hallinnan perhenäkymästä. Jos näyttö on kosketusnäyttö, hallinnasta voidaan erikseen sallia laitekohtainen kuittausoikeus. Pelkkä katselulaite ei voi kirjoittaa tietoja. Tuplapainallus ei tuplaa suoritusta. Verkkokatkossa kuittaus estetään ja tarjotaan uusi yritys, eikä onnistumista teeskennellä.

## Kioski, käyttöliittymä ja toimintavarmuus

- Virallinen Raspberry Pi OS 64-bit Desktop ja sen pakettivaraston Chromium. Automaattinen käynnistys rajatun kiosk-käyttäjän istuntoon, selaimen uudelleenkäynnistys kaatumisen jälkeen ja aikapalvelu käyttöön. [Raspberry Pi OS](https://www.raspberrypi.com/software/operating-systems/)
- Näyttö paritetaan lyhytikäisellä kertakoodilla hallinnassa. Jatkuva laiteistunto on rajattu näyttöön ja voidaan peruuttaa. Tunnistetta ei pidetä URL-osoitteessa.
- Näyttö hakee paikallisen palvelimen yhdistelmänäkymän 30 sekunnin välein ja laskee vartin vaihtumisen ajastimella. Sivun palautuessa taustalta tiedot tarkistetaan heti.
- Viimeinen onnistunut näkymä säilyy selaimen muistissa avoimen istunnon ajan. Ensiversio ei lupaa offline-käynnistystä palvelimen ollessa poissa. Käynnistysvirhe näyttää paikallisen yhteysilmoituksen ja yrittää yhteyttä uudelleen; tähän tehdään Pi:lle pieni paikallinen aloitussivu.
- Kalenterin ja askareiden tietoja ei pysyvästi välimuistiteta Pi:n levylle ensimmäisessä versiossa. Voimassaolo tarkistetaan myös muistissa olevalta sisällöltä.
- Hallinta määrittää hiljaiset ajat ja näytön himmennyksen. Fyysinen näytön virrankatkaisu vaatii HDMI/laitetuen tarkistuksen; selaimen tumma näkymä ei yksin säästä taustavalon sähköä.
- Suuret tekstit, selkeä kontrasti, ei vilkkumista. Fontit ja sovellusresurssit paikallisesti, ei ulkoisia CDN-riippuvuuksia. HTML `lang=fi`, päivät DD.MM.YYYY, kellot 24 h, desimaalipilkku, °C ja m/s.
- Jokaisella kortilla lataus-, tyhjä-, virhe- ja vanhentumistila. Muiden korttien toiminta jatkuu yhden tietolähteen epäonnistuessa.

## Toteutusjärjestys ja hyväksyntä

1. Varmista laitteisto, Foreca-tilaus ja sähkörajapinnan käyttöoikeus. Tarkista aidot testivastaukset.
2. Toteuta Laravel-palvelin, suomenkielinen neljän kortin näyttö ja hallinnan esikatselu.
3. Lisää varttihinnat, Foreca, Google-kalenteri ja paikalliset kotityöt.
4. Lisää paritus, käyttöoikeudet, häiriötilat ja kiosk-käynnistys.
5. Testaa oikealla Pi:llä vähintään 48 tuntia ja tee varmuuskopion palautuskoe.

Hyväksyntä: kaikki neljä korttia toimivat; Lahti ja Google ovat valittuina; asetuksen tallennus näkyy näytöllä viimeistään 60 sekunnissa; vartin väri vaihtuu enintään 5 sekuntia rajan jälkeen; katkennut hintadata ei jää vihreäksi; kesäaika, negatiivinen hinta ja tarkat hintarajat toimivat; suomi näkyy myös virheissä; tietoja luetaan noin 2 metrin päästä; Pi palautuu sähkökatkosta ilman näppäimistöä. Verkkokatko, palvelimen uudelleenkäynnistys ja vanhentunut Google-valtuutus kokeillaan erikseen.

Hallinnan tekninen määrittely: [SPEKSI.md](../hallintapaneeli/SPEKSI.md). Varmennetut versiot ja lähteet: [PAKETIT-JA-LAHTEET.md](../hallintapaneeli/PAKETIT-JA-LAHTEET.md).
