CREATE TABLE beratung
(
	  id SERIAL NOT NULL,
	  mailid TEXT NOT NULL,
	  maildate TIMESTAMP WITH time zone,
	  replyid TEXT,
	  replydate TIMESTAMP WITH time zone,
	  subject TEXT,
	  isreply BOOLEAN,
	  ignore BOOLEAN,
	  sender TEXT,
	  CONSTRAINT pk_beratung PRIMARY KEY (id)
);
