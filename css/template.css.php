
html{
  background: black url(/images/planet2.jpg) 0 80px no-repeat;
}


body {
  background: transparent url(/images/planet3.jpg) bottom right no-repeat;
  font-family: Verdana, Arial, Helvetica, sans-serif;
  font-size: 13px;
  color: white;
  margin:0;
  padding:0;
}

a {
  color: #D3E8F6;
  background-color: transparent;
  font-weight: normal;
  text-decoration:none;
}

a:hover{
  background-color: #163A65;
  color:white;
}

h1 {
  color: white;
  font-size: 16px;
  line-height:15px;
  font-weight: bold;
  margin-top:0;
}

code {
  font-family: Monaco, Verdana, Sans-serif;
  font-size: 12px;
  background-color: #f9f9f9;
  border: 1px solid #D0D0D0;
  color: #002166;
  display: block;
  margin: 14px 0 14px 0;
  padding: 12px 10px 12px 10px;
}

table{
  border-collapse:collapse;
  border-spacing:0;
  width:100%;
}

table th{
  border-bottom: 2px solid #787878;
  background:#555556 url(/images/headingfade.gif) top left repeat-x;
  color:white;
  text-align:left;
  font-weight:bold;
}

td, th{
  padding:5px;
  font-size:12px;
  color:white;
}

td{
  background:#4e4e4e;
}

table tr:nth-child(2n) td{
  background:#454545;
}


label{
  display:block;
  float:left;
  width:150px;
  text-align: right;
  padding-right:10px;
  text-transform:capitalize;
}

#userbox{
  position:absolute;
  width:380px;
  height:60px;
  background: transparent url(/images/userbox.png) top left no-repeat;
  top:10px;
  right:10px;
  padding:10px;
}

#userbox .avatar{
  float:right;
}

#background{
  background: transparent url(/images/head1back.gif) 0 76px repeat-x;
  min-height:800px;
  padding-top:100px;
}

#container{
  width:950px;
  margin:0px auto;
}

#menu{
  margin:0;
  padding:0;
  height:38px;
  position:relative;
}

#menu li{
  margin:0;
  padding:0;
  list-style:none;
  display:block;
  float:left;
  margin-right:-22px;
  height:38px;
}

#menu li a{
  display:block;
  height:38px;
  width:152px;
  background:url(/images/home2.gif) top left no-repeat;
  text-indent:-999em;
}

#menu li.home{
  width:128px;
}

#menu li.planets{
  width:152px;
}

#menu li.fleets{
  width:152px;
}

#menu li.navigation{
  width:152px;
}

#menu li.research{
  width:152px;
}

#menu li.alliances{
  width:152px;
}


#menu li.home a{
  width:128px;
}

#menu li.planets a{
  width:152px;
  background:url(/images/planets.gif) top left no-repeat;
}

#menu li.fleets a{
  width:152px;
  background:url(/images/fleets.gif) top left no-repeat;
}

#menu li.navigation a{
  width:152px;
  background:url(/images/navigation.gif) top left no-repeat;
}

#menu li.research a{
  width:152px;
  background:url(/images/research.gif) top left no-repeat;
}

#menu li.alliances a{
  width:152px;
  background:url(/images/alliances.gif) top left no-repeat;
}

#menu li a:hover{
  background-position: -212px 0;
}


#menu li a.active{
  background-position: -424px 0;
  z-index:999;
  position:absolute;
}

.lower-content{
  background:#393939;
  padding:1px;
}

.content{
  background:#2C2C2C url(/images/head2back.gif) top left repeat-x;
  padding:10px;
  border:1px solid black;
  margin-bottom:10px;
  height:50px;
}

.headbar{
  height:24px;
  padding:5px 10px;
}

.headbar p{
  margin:0;
  line-height:20px;
}

.menu{

}

.footer{
  text-align:center;
  height:24px;
  margin:0;
}