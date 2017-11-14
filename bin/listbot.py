#!/usr/bin/env python

import psycopg2, sys, email, time, datetime
from email import parser
from database import connstr
import logging

logging.basicConfig(filename='/tmp/maillimbo.log',level=logging.DEBUG)

logging.info("maillimbo starting at {}".format(time.asctime(time.localtime(time.time()))))

with psycopg2.connect(connstr) as conn:
    with conn.cursor() as cur:

        # Read from STDIN into array of lines.
        email_input = sys.stdin.readlines()

        # email.parsers.FeedParser.feed() expects to receive lines one at a time
        # msg holds the complete email Message object
        feedparser = parser.FeedParser()
        msg = None
        for msg_line in email_input:
            feedparser.feed(msg_line)
        msg = feedparser.close()

        if msg.has_key('Message-ID') and msg.has_key('List-Id'):
            msgid = msg.get('Message-ID')
            listid = msg.get('List-Id')
            sender = msg.get('From')
            logging.debug("Handling mail {} ({})".format(msgid, listid))
            if '<beratung.lists.fsinf.at>' in listid:
                msgdate = datetime.datetime.fromtimestamp(time.mktime(email.utils.parsedate(msg.get('Date'))))
                subject = msg.get('Subject')
                if msg.has_key('In-Reply-To'):
                    # this is an answer to an other mail
                    cur.execute("INSERT INTO beratung (mailid, maildate, isreply, subject, sender) VALUES (%s, %s, %s, %s, %s)", (msgid, msgdate, True, subject, sender))
                    replytoid = msg.get('In-Reply-To')
                    cur.execute("SELECT replyid FROM beratung WHERE mailid=%s", (replytoid,))
                    res = cur.fetchone()
                    if res is None:
                        logging.debug("Mail being answered to could not be found.")
                    elif res[0] is None:
                        logging.debug("There has been no answer so far, store this one")
                        cur.execute("UPDATE beratung SET replyid=%s, replydate=%s WHERE mailid=%s", (msgid, msgdate, replytoid))
                    else:
                        logging.debug("Mail has already been answered, skipping")
                else:
                    cur.execute("INSERT INTO beratung (mailid, maildate, isreply, subject, sender) VALUES (%s, %s, %s, %s, %s)", (msgid, msgdate, False, subject, sender))

        logging.debug("Message: {}".format(msg.as_string()))

        conn.commit()
