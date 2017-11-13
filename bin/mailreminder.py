#!/usr/bin/env python

import psycopg2
from database import connstr

# Import smtplib for the actual sending function
import smtplib

# Import the email modules we'll need
from email.mime.text import MIMEText
from email.header import decode_header

with psycopg2.connect(connstr) as conn:
    with conn.cursor() as cur:

        cur.execute("SELECT id,maildate,subject,sender FROM beratung WHERE replyid is NULL AND isreply is FALSE AND ignore is FALSE ORDER BY maildate DESC;")

        maillist = []
        count = 0

        for (x_id, x_maildate, x_subject, x_sender) in cur.fetchall():
            subjects = " ".join([x[0] for x in decode_header(x_subject)])
            senders = " ".join([x[0] for x in decode_header(x_sender)])
            maillist.append("%s: %s (%s)" % (x_maildate, subjects, senders))
            count = count + 1

        mailheader = ['Hallo Du.', '', 'Da sind %s mails, die beantwortet werden wollen.' % count, '', 'Und zwar:', '']
        mailfooter = ['', 'Falls diese Mails nicht mehr relevant sind (Spam o.ä.) können diese unter http://ionic.at/beratung/ als erledigt markiert werden.','','','Mehr Infos gibts auf https://intern.fsinf.at/wiki/Beratung_per_Mail']
        mailtext = mailheader + maillist + mailfooter

        msg = MIMEText('\n'.join(mailtext), 'plain', 'UTF-8')

        # me == the sender's email address
        # you == the recipient's email address
        msg['Subject'] = 'Beratungsmail-Reminder: %s unanswered mails' % (count)
        msg['From'] = 'listbot@ionic.at'
        msg['Reply-To'] = 'astra+listbot@ionic.at'
        msg['To'] = 'fsinf@fsinf.at'

        if count > 0:
            # Send the message via our own SMTP server, but don't include the
            # envelope header.
            s = smtplib.SMTP('localhost')
            s.sendmail(msg['From'], [msg['To']], msg.as_string())
            s.quit()

        conn.commit()
