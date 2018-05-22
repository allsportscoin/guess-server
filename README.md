# About this project
This project is the Server of the Soc guess project, built in php. We use mysql and redis to store some tmp data for preformance.
Use Yaf to routing the http request.


## Directory

```
.
├── README.md
├── config     // project config
├── library       // some local api
├── webroot/index.php    // entry file
├── nodules/Inner/controllers  //code for request controllers
├── scripts/contract_guess.js    // abi of the guess contract
├── scripts/contract_soc.js   //abi of the soc contract
├── scripts/createTx_guess.js    // put guess info to block chain
├── scripts/createTx_soc.js    //send soc to guess winner
├── scripts/package.json    //some dependencies pkgs, use npm install to get all dependencies.
├── service   //main logic of this project
└── Bootstrap.php
```


