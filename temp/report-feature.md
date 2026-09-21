Aku telah mengimplementasi fitur "Report" pada saat klik button "Submit Report" di "Stock Report" atau `/stock-report?outlet=xx` page. Tapi ada bug atau cacat, yaitu saat di klik button "Submit Report", setelah pergi ke "Report" atau `/reports` page, yang ke report hanya item yang berstatus "Out of Stock" saja. Sedangkan misal item yang "Ready" tidak ke report. 

Jadi aku ingin di tab "Report" atau `/reports` page itu, semua item ke report. Jadi tidak hanya yang "Out of Stock" saja namun juga item yang "Ready" dengan status "Almost Out" juga. Paham maksudku ga?

Dont drop any tables on DB pls.

Sip better lah, tapi masih ada bug atau error. Nah tadi gw nyoba buat report as Staff ya (no login, saat "Select Outlet" lalu ke redirect ke `/stock-report?outlet=xx` page), saat report barang "Ice" gw set ke "Almost Out" dan saat di "Report" page masih "Ready" which is okay make sense. Tapi pas item "Susu UHT" gw set ke "Out of Stock", kenapa saat balik ke Outlet as Staff, item "Susu UHT" nya masih berstatus "Ready". Wtf, ga masuk akal kan?


Sipp mantab, sekarang minta tolong benerin Card di "Dashboard" atau `/dashboard` page. Nah disitu ada dua card, "Almost Out" dan "Out of Stock".

Sip mantab, tapi sekarang ada sebuah fitur yang saya rasa kurang suka, yaitu saat di "Stock Report" atau `/stock-report?outlet=xx` page (as Staff), misal katakanlah selesai klik button "Submit Report". Nah kenapa harus ke redirect ke "Report" atau `/reports` page (As Manager). 

Jadi saya ingin, setelah role Staff (Outlet Option -> Stock Report) selesai mereport, yaudah tetap di page itu. Paham ga?

Jadi yang bisa lihat di page "Dahsboard" kan hanya role "Manager"